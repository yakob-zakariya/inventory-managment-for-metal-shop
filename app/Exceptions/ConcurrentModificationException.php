<?php

namespace App\Exceptions;

use Exception;

class ConcurrentModificationException extends Exception
{
    public static function balanceChanged(float $expected, float $actual): self
    {
        return new self(
            'Remaining balance changed during transaction. '.
            "Expected {$expected} ETB, found {$actual} ETB. ".
            'Please refresh and try again.'
        );
    }
}
