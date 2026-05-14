<?php

namespace App\Enums;

enum AccountType: string
{
    case CASH = 'cash';
    case BANK = 'bank';
    case MOBILE_MONEY = 'mobile_money';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::BANK => 'Bank',
            self::MOBILE_MONEY => 'Mobile Money',
        };
    }
}
