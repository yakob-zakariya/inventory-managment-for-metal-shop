<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalTransaction extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'type',
        'amount',
        'transaction_date',
        'category',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /**
     * Get the account that owns the capital transaction
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user who created the transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
