<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Receivable;
use App\Models\Sale;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function completeSale(Sale $sale): void
    {
        // Prevent duplicate processing
        if ($sale->status === SaleStatus::COMPLETED && $sale->stockMovements()->count() > 0) {
            return; // Already processed
        }

        DB::transaction(function () use ($sale) {
            // Validate sale can be completed
            if ($sale->status === SaleStatus::CANCELLED) {
                throw new Exception('Cannot complete a cancelled sale.');
            }

            // Validate stock availability for all items
            $items = $sale->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->toArray();

            $stockErrors = $this->stockService->validateStockAvailability($items);

            if (! empty($stockErrors)) {
                throw new Exception('Insufficient stock: '.implode(', ', $stockErrors));
            }

            // Update sale status (without triggering observer again)
            $sale->updateQuietly([
                'status' => SaleStatus::COMPLETED,
            ]);

            // Create stock movements for each item
            foreach ($sale->items as $item) {
                $this->stockService->createMovement(
                    product: $item->product,
                    type: StockMovementType::SALE,
                    quantityIn: 0,
                    quantityOut: $item->quantity,
                    reference: $sale,
                    notes: "Sale #{$sale->id} completed",
                    transactionDate: $sale->sale_date
                );
            }

            // ✅ NO RECEIVABLE CREATION HERE
            // Receivable is already created in CreateSale::afterCreate() for credit sales
            // This prevents duplicate receivables
        });
    }

    public function cancelSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            // Validate sale can be cancelled
            if ($sale->status === SaleStatus::CANCELLED) {
                return; // Already cancelled
            }

            $wasCompleted = $sale->status === SaleStatus::COMPLETED;

            // Update sale status (without triggering observer again)
            $sale->updateQuietly([
                'status' => SaleStatus::CANCELLED,
            ]);

            // If sale was completed, reverse stock movements
            if ($wasCompleted) {
                foreach ($sale->items as $item) {
                    $this->stockService->createMovement(
                        product: $item->product,
                        type: StockMovementType::ADJUSTMENT,
                        quantityIn: $item->quantity,
                        quantityOut: 0,
                        reference: $sale,
                        notes: "Sale #{$sale->id} cancelled - restocking items",
                        transactionDate: now()->toDateString()
                    );
                }
            }
        });
    }

    /**
     * Reopen a cancelled sale (change back to draft)
     */
    public function reopenSale(Sale $sale): void
    {
        if ($sale->status !== SaleStatus::CANCELLED) {
            throw new Exception('Only cancelled sales can be reopened.');
        }

        $sale->update([
            'status' => SaleStatus::DRAFT,
        ]);
    }
}
