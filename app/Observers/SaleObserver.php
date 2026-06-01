<?php

namespace App\Observers;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SaleObserver
{
    /**
     * Handle the Sale "updating" event.
     *
     * This runs BEFORE the sale is saved to the database.
     * It validates account balance for CASH sales when total increases.
     *
     * Bug Fix:
     * - Similar to Bug 6 for purchases (account balance validation)
     *
     * Requirements: 2.17, 2.18, 2.19 (adapted for sales)
     */
    public function updating(Sale $sale): void
    {
        // Only validate CASH sales
        if ($sale->payment_type !== PaymentType::CASH) {
            return;
        }

        $payment = $sale->payments()->first();
        if (! $payment) {
            return; // No payment to validate
        }

        // Calculate old and new totals
        $oldTotal = $payment->amount;
        $newTotal = $sale->total_amount;
        $delta = $newTotal - $oldTotal;

        // Validate account balance if total is increasing
        // For sales, we're RECEIVING money, so we don't need to validate balance
        // This is different from purchases where we're SPENDING money
        // So we can skip this validation for sales
    }

    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "updated" event.
     *
     * This runs AFTER the sale is saved to the database.
     * It synchronizes Payment and Receivable records when the total_amount changes.
     * It also updates stock movements when items change on completed sales.
     *
     * Note: We use 'updated' instead of 'updating' because total_amount is a computed
     * property (accessor) that sums the items. The items are saved after the sale,
     * so we need to wait until after the update to get the correct new total.
     *
     * Bug Fixes:
     * - Bug 5: Credit sale editing receivable synchronization
     * - Bug 3: Stock movement synchronization for completed sales
     *
     * Requirements: 2.14, 2.15, 2.16, 2.8, 2.9, 2.10
     */
    public function updated(Sale $sale): void
    {
        // Calculate old total from payment/receivable (before items were updated)
        $oldTotal = null;

        if ($sale->payment_type === PaymentType::CASH) {
            $payment = $sale->payments()->first();
            if ($payment) {
                $oldTotal = $payment->amount;
            }
        } elseif ($sale->payment_type === PaymentType::CREDIT) {
            $receivable = $sale->receivable;
            if ($receivable) {
                $oldTotal = $receivable->amount;
            }
        }

        // Get new total from items (after update)
        $newTotal = $sale->total_amount;

        // Handle payment/receivable synchronization if total changed
        if ($oldTotal !== null && $oldTotal != $newTotal) {
            $delta = $newTotal - $oldTotal;

            // Handle CASH sales - synchronize payment and account balance
            if ($sale->payment_type === PaymentType::CASH) {
                $payment = $sale->payments()->first();

                if ($payment) {
                    // Update payment amount and adjust account balance
                    DB::transaction(function () use ($payment, $newTotal, $delta) {
                        // Update payment amount to match new total
                        $payment->amount = $newTotal;
                        $payment->save();

                        // Adjust account balance by delta
                        // For sales: if delta > 0 (increase): add more to balance
                        // For sales: if delta < 0 (decrease): reduce balance
                        $account = $payment->account;
                        $account->balance += $delta;
                        $account->save();
                    });
                }
            }

            // Handle CREDIT sales - synchronize receivable
            if ($sale->payment_type === PaymentType::CREDIT) {
                $receivable = $sale->receivable;

                if ($receivable) {
                    // Bug 5 Fix: Update receivable amount and remaining_balance
                    DB::transaction(function () use ($receivable, $newTotal, $delta) {
                        // Update receivable amount to match new total
                        $receivable->amount = $newTotal;

                        // Adjust remaining_balance by delta
                        // This preserves any partial payments that have been made
                        $receivable->remaining_balance += $delta;

                        $receivable->save();
                    });
                }
            }
        }

        // Handle stock movement synchronization for COMPLETED sales
        // When items change on a completed sale, we need to update stock
        if ($sale->status === SaleStatus::COMPLETED) {
            DB::transaction(function () use ($sale) {
                // CRITICAL: Refresh the items relationship to get the latest data
                $sale->load('items');

                // Get all current stock movements for this sale
                $existingMovements = $sale->stockMovements()
                    ->where('type', StockMovementType::SALE)
                    ->get();

                // Build a map of existing movements by product_id
                $existingByProduct = $existingMovements->keyBy('product_id');

                // Build a map of current items by product_id
                $currentItems = $sale->items->keyBy('product_id');

                // Check if items have changed
                $itemsChanged = $existingByProduct->count() !== $currentItems->count() ||
                    $existingByProduct->keys()->diff($currentItems->keys())->isNotEmpty();

                // Also check if any quantities changed
                if (! $itemsChanged) {
                    foreach ($currentItems as $productId => $item) {
                        $existingMovement = $existingByProduct->get($productId);
                        if ($existingMovement && $existingMovement->quantity_out != $item->quantity) {
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
                        $oldQuantity = $existingMovement->quantity_out;
                        $newQuantity = $item->quantity;

                        if ($oldQuantity != $newQuantity) {
                            // Update the stock movement
                            // The StockMovement model's booted() event will automatically update product stock
                            $existingMovement->quantity_out = $newQuantity;
                            $existingMovement->notes = "Sale #{$sale->id} updated - quantity changed from {$oldQuantity} to {$newQuantity}";
                            $existingMovement->save();
                        }
                    } else {
                        // New item added - create stock movement
                        // The StockMovement model's booted() event will automatically update product stock
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'reference_type' => get_class($sale),
                            'reference_id' => $sale->id,
                            'type' => StockMovementType::SALE,
                            'quantity_in' => 0,
                            'quantity_out' => $item->quantity,
                            'transaction_date' => $sale->sale_date ?? now()->toDateString(),
                            'notes' => "Sale #{$sale->id} updated - new item added",
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
     * Handle the Sale "deleting" event.
     *
     * This runs BEFORE the sale is deleted from the database.
     * It cleans up related records and restores account balances.
     *
     * Cleanup actions:
     * 1. Restore account balance from payments (for cash sales, we reduce the balance)
     * 2. Delete associated payments
     * 3. Delete stock movements and manually recalculate product stock
     * 4. Delete receivables for credit sales
     */
    public function deleting(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            // 1. Adjust account balances and delete payments for CASH sales
            foreach ($sale->payments as $payment) {
                $account = $payment->account;
                // For sales, we received money, so we need to REDUCE the balance when deleting
                $account->balance -= $payment->amount;
                $account->save();
            }

            // Delete all payments
            $sale->payments()->delete();

            // 2. Delete stock movements and manually recalculate product stock
            // Get affected products before deleting movements
            $affectedProductIds = $sale->stockMovements()->pluck('product_id')->unique();

            // Delete the stock movements
            $sale->stockMovements()->delete();

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

            // 3. Delete receivables for CREDIT sales
            if ($sale->receivable) {
                $sale->receivable->delete();
            }
        });
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "force deleted" event.
     */
    public function forceDeleted(Sale $sale): void
    {
        //
    }
}
