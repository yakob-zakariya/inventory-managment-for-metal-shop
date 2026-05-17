<?php

namespace App\Exceptions;

use Exception;

class PaymentValidationException extends Exception
{
    public static function amountExceedsBalance(float $amount, float $balance): self
    {
        return new self(
            "Payment amount {$amount} ETB exceeds remaining balance {$balance} ETB"
        );
    }

    public static function invalidAmount(float $amount): self
    {
        return new self(
            "Payment amount must be greater than zero, got {$amount} ETB"
        );
    }

    public static function noAccountsAvailable(): self
    {
        return new self(
            'No accounts available for payment'
        );
    }
}
