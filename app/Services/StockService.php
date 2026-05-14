<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockService
{
    /**
     * Create a stock movement
     */
    public function createMovement(
        Product $product,
        StockMovementType $type,
        float $quantityIn = 0,
        float $quantityOut = 0,
        ?Model $reference = null,
        ?string $notes = null,
        ?string $transactionDate = null
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'user_id' => Auth::id(),
            'transaction_date' => $transactionDate ?? now()->toDateString(),
            'type' => $type,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Get current stock for a product
     */
    public function getCurrentStock(Product $product): float
    {
        $totalIn = StockMovement::where('product_id', $product->id)->sum('quantity_in');
        $totalOut = StockMovement::where('product_id', $product->id)->sum('quantity_out');
        
        return $totalIn - $totalOut;
    }

    /**
     * Check if product has sufficient stock
     */
    public function hasSufficientStock(Product $product, float $quantity): bool
    {
        return $this->getCurrentStock($product) >= $quantity;
    }

    /**
     * Validate stock availability for multiple products
     */
    public function validateStockAvailability(array $items): array
    {
        $errors = [];

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $quantity = $item['quantity'];

            if (!$this->hasSufficientStock($product, $quantity)) {
                $currentStock = $this->getCurrentStock($product);
                $errors[] = "Insufficient stock for {$product->name}. Available: {$currentStock}, Required: {$quantity}";
            }
        }

        return $errors;
    }
}
