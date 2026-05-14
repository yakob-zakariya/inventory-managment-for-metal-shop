<?php

namespace App\Enums;

enum PaymentType: string
{
    case CASH = 'cash';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::CREDIT => 'Credit',
        };
    }
}
