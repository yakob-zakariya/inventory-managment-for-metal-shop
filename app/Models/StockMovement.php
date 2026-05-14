<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'transaction_date',
        'type',
        'quantity_in',
        'quantity_out',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'type' => StockMovementType::class,
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:2',
        'quantity_out' => 'decimal:2',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Automatically update product stock when stock movement is created/updated/deleted
    protected static function booted(): void
    {
        static::created(function (StockMovement $movement) {
            $movement->updateProductStock();
        });

        static::updated(function (StockMovement $movement) {
            $movement->updateProductStock();
        });

        static::deleted(function (StockMovement $movement) {
            $movement->updateProductStock();
        });
    }

   /**
 * Update the product's current_stock based on all stock movements
 */
public function updateProductStock(): void
{
    $product = $this->product;
    
    $totalIn = StockMovement::where('product_id', $product->id)->sum('quantity_in');
    $totalOut = StockMovement::where('product_id', $product->id)->sum('quantity_out');
    
    // Use direct property assignment instead of update() to bypass fillable
    $product->current_stock = $totalIn - $totalOut;
    $product->save();
}

}
