<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'balance',
        'description',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'balance' => 'decimal:2',
    ];

    // Relationships
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class);
    }
}
