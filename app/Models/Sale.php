<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'sale_date',
        'payment_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'payment_type' => PaymentType::class,
        'status' => SaleStatus::class,
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    // Computed Properties
    public function getTotalAmountAttribute(): float
    {
        return $this->items()->sum('total_price');
    }

    // 🔜 Uncomment when Payment model is created
    // public function getPaidAmountAttribute(): float
    // {
    //     return $this->payments()->sum('amount');
    // }

    // 🔜 Uncomment when Payment model is created
    // public function getRemainingBalanceAttribute(): float
    // {
    //     return $this->total_amount - $this->paid_amount;
    // }

    // Check if this is a walk-in customer
    public function getIsWalkInAttribute(): bool
    {
        return $this->customer_id === null;
    }
}
