<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function cents(mixed $value): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['amount' => 'Use a non-negative rupee amount with at most two decimal places.']);
        }
        [$whole,$fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
