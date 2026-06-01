<?php

namespace App\Observers;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseObserver
{
    /**
     * Handle the Purchase "updating" event.
     *
     * This runs BEFORE the purchase is saved to the database.
     * It validates account balance for CASH purchases when total increases.
     *
     * Bug Fix:
     * - Bug 6: Purchase editing account balance validation
     *
     * Requirements: 2.17, 2.18, 2.19
     */
    public function updating(Purchase $purchase): void
    {
        // Only validate CASH purchases
        if ($purchase->payment_type !== PaymentType::CASH) {
            return;
        }

        $payment = $purchase->payments()->first();
        if (! $payment) {
            return; // No payment to validate
        }

        // Calculate old and new totals
        $oldTotal = $payment->amount;
        $newTotal = $purchase->total_amount;
        $delta = $newTotal - $oldTotal;

        // Bug 6 Fix: Validate account balance if total is increasing
        if ($delta > 0) {
            $account = $payment->account;

            if ($account->balance < $delta) {
                throw new Exception(
                    "Insufficient balance. Account '{$account->name}' has only ETB ".number_format($account->balance, 2).
                    ', but additional ETB '.number_format($delta, 2).' is required.'
                );
            }
        }
    }

    /**
     * Handle the Purchase "created" event.
     */
    public function created(Purchase $purchase): void
    {
        //
    }

    /**
     * Handle the Purchase "updated" event.
     *
     * This runs AFTER the purchase is saved to the database.
     * It synchronizes Payment and Payable records when the total_amount changes.
     * It also updates stock movements when items change on completed purchases.
     *
     * Note: We use 'updated' instead of 'updating' because total_amount is a computed
     * property (accessor) that sums the items. The items are saved after the purchase,
     * so we need to wait until after the update to get the correct new total.
     *
     * Bug Fixes:
     * - Bug 2: Cash purchase editing payment synchronization
     * - Bug 4: Credit purchase editing payable synchronization
     * - Stock movement synchronization for completed purchases
     *
     * Requirements: 2.4, 2.5, 2.6, 2.7, 2.11, 2.12, 2.13
     */
    public function updated(Purchase $purchase): void
    {
        // Calculate old total from payment/payable (before items were updated)
        $oldTotal = null;

        if ($purchase->payment_type === PaymentType::CASH) {
            $payment = $purchase->payments()->first();
            if ($payment) {
                $oldTotal = $payment->amount;
            }
        } elseif ($purchase->payment_type === PaymentType::CREDIT) {
            $payable = $purchase->payable;
            if ($payable) {
                $oldTotal = $payable->amount;
            }
        }

        // Get new total from items (after update)
        $newTotal = $purchase->total_amount;

        // Handle payment/payable synchronization if total changed
        if ($oldTotal !== null && $oldTotal != $newTotal) {
            $delta = $newTotal - $oldTotal;

            // Handle CASH purchases - synchronize payment and account balance
            if ($purchase->payment_type === PaymentType::CASH) {
                $payment = $purchase->payments()->first();

                if ($payment) {
                    // Bug 2 Fix: Update payment amount and adjust account balance
                    DB::transaction(function () use ($payment, $newTotal, $delta) {
                        // Update payment amount to match new total
                        $payment->amount = $newTotal;
                        $payment->save();

                        // Adjust account balance by delta
                        // If delta > 0 (increase): reduce balance further
                        // If delta < 0 (decrease): restore balance
                        $account = $payment->account;
                        $account->balance -= $delta;
                        $account->save();
                    });
                }
            }

            // Handle CREDIT purchases - synchronize payable
            if ($purchase->payment_type === PaymentType::CREDIT) {
                $payable = $purchase->payable;

                if ($payable) {
                    // Bug 4 Fix: Update payable amount and remaining_balance
                    DB::transaction(function () use ($payable, $newTotal, $delta) {
                        // Update payable amount to match new total
                        $payable->amount = $newTotal;

                        // Adjust remaining_balance by delta
                        // This preserves any partial payments that have been made
                        $payable->remaining_balance += $delta;

                        $payable->save();
                    });
                }
            }
        }

        // Handle stock movement synchronization for COMPLETED purchases
        // When items change on a completed purchase, we need to update stock
        if ($purchase->status === PurchaseStatus::COMPLETED) {
            DB::transaction(function () use ($purchase) {
                // CRITICAL: Refresh the items relationship to get the latest data
                $purchase->load('items');

                // Get all current stock movements for this purchase
                $existingMovements = $purchase->stockMovements()
                    ->where('type', StockMovementType::PURCHASE)
                    ->get();

                // Build a map of existing movements by product_id
                $existingByProduct = $existingMovements->keyBy('product_id');

                // Build a map of current items by product_id
                $currentItems = $purchase->items->keyBy('product_id');

                // Check if items have changed
                $itemsChanged = $existingByProduct->count() !== $currentItems->count() ||
                    $existingByProduct->keys()->diff($currentItems->keys())->isNotEmpty();

                // Also check if any quantities changed
                if (! $itemsChanged) {
                    foreach ($currentItems as $productId => $item) {
                        $existingMovement = $existingByProduct->get($productId);
                        if ($existingMovement && $existingMovement->quantity_in != $item->quantity) {
                            $itemsChanged = true;
                            break;
                        }
                    }
                }

                // Only proceed if items actually changed
                if (! $itemsChanged) {
                    return;
                }

                // Process each current item
                foreach ($currentItems as $productId => $item) {
                    $existingMovement = $existingByProduct->get($productId);

                    if ($existingMovement) {
                        // Item exists - check if quantity changed
                        $oldQuantity = $existingMovement->quantity_in;
                        $newQuantity = $item->quantity;

                        if ($oldQuantity != $newQuantity) {
                            // Update the stock movement
                            // The StockMovement model's booted() event will automatically update product stock
                            $existingMovement->quantity_in = $newQuantity;
                            $existingMovement->notes = "Purchase #{$purchase->id} updated - quantity changed from {$oldQuantity} to {$newQuantity}";
                            $existingMovement->save();
                        }
                    } else {
                        // New item added - create stock movement
                        // The StockMovement model's booted() event will automatically update product stock
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'reference_type' => get_class($purchase),
                            'reference_id' => $purchase->id,
                            'type' => StockMovementType::PURCHASE,
                            'quantity_in' => $item->quantity,
                            'quantity_out' => 0,
                            'transaction_date' => $purchase->received_date ?? now()->toDateString(),
                            'notes' => "Purchase #{$purchase->id} updated - new item added",
                        ]);
                    }
                }

                // Process removed items (items that were in stock movements but not in current items)
                foreach ($existingByProduct as $productId => $movement) {
                    if (! $currentItems->has($productId)) {
                        // Item was removed - delete the stock movement
                        // The StockMovement model's booted() event will automatically update product stock
                        $movement->delete();
                    }
                }
            });
        }
    }

    /**
     * Handle the Purchase "deleting" event.
     *
     * This runs BEFORE the purchase is deleted from the database.
     * It cleans up related records and restores account balances.
     *
     * Cleanup actions:
     * 1. Restore account balance from payments
     * 2. Delete associated payments
     * 3. Delete stock movements and manually recalculate product stock
     * 4. Delete payables for credit purchases
     */
    public function deleting(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            // 1. Restore account balances and delete payments for CASH purchases
            foreach ($purchase->payments as $payment) {
                $account = $payment->account;
                $account->balance += $payment->amount;
                $account->save();
            }

            // Delete all payments
            $purchase->payments()->delete();

            // 2. Delete stock movements and manually recalculate product stock
            // Get affected products before deleting movements
            $affectedProductIds = $purchase->stockMovements()->pluck('product_id')->unique();

            // Delete the stock movements
            $purchase->stockMovements()->delete();

            // Manually recalculate stock for each affected product
            foreach ($affectedProductIds as $productId) {
                $product = Product::find($productId);
                if ($product) {
                    $totalIn = StockMovement::where('product_id', $productId)->sum('quantity_in');
                    $totalOut = StockMovement::where('product_id', $productId)->sum('quantity_out');
                    $product->current_stock = $totalIn - $totalOut;
                    $product->save();
                }
            }

            // 3. Delete payables for CREDIT purchases
            if ($purchase->payable) {
                $purchase->payable->delete();
            }
        });
    }

    /**
     * Handle the Purchase "restored" event.
     */
    public function restored(Purchase $purchase): void
    {
        //
    }

    /**
     * Handle the Purchase "force deleted" event.
     */
    public function forceDeleted(Purchase $purchase): void
    {
        //
    }
}
