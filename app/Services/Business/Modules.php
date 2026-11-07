<?php

namespace App\Services\Business;

use Illuminate\Support\Facades\Schema;

class Modules
{
    private array $ready = [];

    public function ready(string $table): bool
    {
        return (bool) config('bizpilot.business_modules_enabled', false) && $this->installed($table);
    }

    public function installed(string $table): bool
    {
        return $this->ready[$table] ??= Schema::hasTable($table);
    }

    public function require(string $table): void
    {
        abort_unless($this->ready($table), 503, 'This module is being installed. Existing invoices remain available.');
    }
}
