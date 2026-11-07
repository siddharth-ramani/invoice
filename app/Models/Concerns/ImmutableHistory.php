<?php

namespace App\Models\Concerns;

trait ImmutableHistory
{
    protected static function bootImmutableHistory(): void
    {
        static::updating(function () {
            throw new \LogicException('History is immutable. Record a new corrective event.');
        });
        static::deleting(function () {
            throw new \LogicException('History must be retained. Record a new corrective event.');
        });
    }
}
