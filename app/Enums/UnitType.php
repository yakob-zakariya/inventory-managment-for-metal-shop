<?php

namespace App\Enums;

enum UnitType: string
{
    case KG = 'kg';
    case PIECE = 'piece';
    case M3 = 'm3';
    case BAG = 'bag';
    case LITER = 'liter';

    public function label(): string
    {
        return match($this) {
            self::KG => 'Kilogram',
            self::PIECE => 'Piece',
            self::M3 => 'Cubic Meter',
            self::BAG => 'Bag',
            self::LITER => 'Liter',
        };
    }
}
