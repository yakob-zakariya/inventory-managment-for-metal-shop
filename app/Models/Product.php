<?php

namespace App\Models;

use App\Enums\UnitType;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'minimum_stock_alert',
        'image',
    ];

    protected $casts = [
        'unit' => UnitType::class, // ✅ Cast to enum
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'current_stock' => 'decimal:2', // ✅ ADD THIS
        'minimum_stock_alert' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
    return $this->hasMany(StockMovement::class);
    }

      // ✅ Helper: Check if stock is low
    public function isLowStock(): bool
    {
        if ($this->minimum_stock_alert === null) {
            return false;
        }
        return $this->current_stock <= $this->minimum_stock_alert;
    }

}
