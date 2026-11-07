<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->decimal('original_total', 12, 2);
            $table->string('legacy_status', 20);
            $table->string('origin', 20)->default('legacy_review');
            $table->date('due_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('admin_actor_id')->nullable();
            $table->text('review_note');
            $table->timestamps();
            $table->index(['vendor_id', 'customer_id', 'confirmed_at'], 'receivable_customer_review');
            $table->index(['vendor_id', 'due_date']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('receipt_sequence');
            $table->string('receipt_no', 50);
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('party_snapshot');
            $table->unsignedBigInteger('admin_actor_id')->nullable();
            $table->timestamps();
            $table->unique(['vendor_id', 'idempotency_key']);
            $table->unique(['vendor_id', 'receipt_sequence']);
            $table->unique(['vendor_id', 'receipt_no']);
            $table->index(['vendor_id', 'payment_date']);
            $table->index(['vendor_id', 'customer_id']);
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('invoice_no', 50);
            $table->date('invoice_date');
            $table->unique(['payment_id', 'invoice_id']);
        });
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $table->date('reversal_date');
            $table->text('reason');
            $table->unsignedBigInteger('admin_actor_id')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'reversal_date']);
        });
    }

    public function down(): void
    {
        // Financial history must never be erased by an automatic rollback.
        throw new RuntimeException('Payment ledger rollback is disabled. Use a reviewed forward migration and retain financial records.');
    }
};
