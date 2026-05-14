<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case ADJUSTMENT = 'adjustment';
    case DAMAGE = 'damage';

    public function label(): string
    {
        return match($this) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::ADJUSTMENT => 'Adjustment',
            self::DAMAGE => 'Damage',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PURCHASE => 'success',
            self::SALE => 'info',
            self::ADJUSTMENT => 'warning',
            self::DAMAGE => 'danger',
        };
    }
}
