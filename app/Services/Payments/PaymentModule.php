<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Schema;

class PaymentModule
{
    private ?bool $ready = null;

    private ?bool $installed = null;

    public function installed(): bool
    {
        return $this->installed ??= Schema::hasTable('invoice_receivables');
    }

    public function ready(): bool
    {
        return (bool) config('bizpilot.payments_enabled', false) && ($this->ready ??= collect(['invoice_receivables', 'payments', 'payment_allocations', 'payment_reversals'])->every(fn ($table) => Schema::hasTable($table)));
    }

    public function requireReady(): void
    {
        abort_unless($this->ready(), 503, 'Payment tracking is awaiting its reviewed database setup. Existing invoices remain available.');
    }
}
