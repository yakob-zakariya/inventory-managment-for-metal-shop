<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Purchase;
use App\Models\Payable;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function completePurchase(Purchase $purchase, ?string $receivedDate = null): void
{
    // Prevent duplicate processing
    if ($purchase->status === PurchaseStatus::COMPLETED && $purchase->stockMovements()->count() > 0) {
        return; // Already processed
    }

    DB::transaction(function () use ($purchase, $receivedDate) {
        // Validate purchase can be completed
        if ($purchase->status === PurchaseStatus::CANCELLED) {
            throw new Exception('Cannot complete a cancelled purchase.');
        }

        // Update purchase status (without triggering observer again)
        $purchase->updateQuietly([
            'status' => PurchaseStatus::COMPLETED,
            'received_date' => $receivedDate ?? $purchase->received_date ?? now()->toDateString(),
        ]);

        // Create stock movements for each item
        foreach ($purchase->items as $item) {
            $this->stockService->createMovement(
                product: $item->product,
                type: StockMovementType::PURCHASE,
                quantityIn: $item->quantity,
                quantityOut: 0,
                reference: $purchase,
                notes: "Purchase #{$purchase->id} completed",
                transactionDate: $purchase->received_date
            );
        }

        // Create payable if payment type is credit
        if ($purchase->payment_type === PaymentType::CREDIT) {
            Payable::create([
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'amount' => $purchase->total_amount,
                'remaining_balance' => $purchase->total_amount,
                'due_date' => $purchase->received_date ? 
                    now()->parse($purchase->received_date)->addDays(30) : 
                    now()->addDays(30),
            ]);
        }
    });
}

public function cancelPurchase(Purchase $purchase): void
{
    DB::transaction(function () use ($purchase) {
        // Validate purchase can be cancelled
        if ($purchase->status === PurchaseStatus::CANCELLED) {
            return; // Already cancelled
        }

        $wasCompleted = $purchase->status === PurchaseStatus::COMPLETED;

        // Update purchase status (without triggering observer again)
        $purchase->updateQuietly([
            'status' => PurchaseStatus::CANCELLED,
        ]);

        // If purchase was completed, reverse stock movements
        if ($wasCompleted) {
            foreach ($purchase->items as $item) {
                $this->stockService->createMovement(
                    product: $item->product,
                    type: StockMovementType::ADJUSTMENT,
                    quantityIn: 0,
                    quantityOut: $item->quantity,
                    reference: $purchase,
                    notes: "Purchase #{$purchase->id} cancelled - reversing stock",
                    transactionDate: now()->toDateString()
                );
            }
        }
    });
}


    /**
     * Reopen a cancelled purchase (change back to pending)
     */
    public function reopenPurchase(Purchase $purchase): void
    {
        if ($purchase->status !== PurchaseStatus::CANCELLED) {
            throw new Exception('Only cancelled purchases can be reopened.');
        }

        $purchase->update([
            'status' => PurchaseStatus::PENDING,
        ]);
    }
}
