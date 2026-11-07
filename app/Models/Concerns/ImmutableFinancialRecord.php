<?php

namespace App\Models\Concerns;

trait ImmutableFinancialRecord
{
    protected static function bootImmutableFinancialRecord(): void
    {
        static::updating(function () {
            throw new \LogicException('Financial records are immutable. Use a controlled payment reversal.');
        });
        static::deleting(function () {
            throw new \LogicException('Financial records must be retained. Use a controlled payment reversal.');
        });
    }
}
