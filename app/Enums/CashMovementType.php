<?php

namespace App\Enums;

enum CashMovementType: string
{
    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return match($this) {
            self::IN => 'In',
            self::OUT => 'Out',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::IN => 'success',
            self::OUT => 'danger',
        };
    }
}
