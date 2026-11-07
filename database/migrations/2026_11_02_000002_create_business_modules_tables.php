<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->string('name');
            $t->string('sku', 80)->nullable();
            $t->string('kind', 12)->default('product');
            $t->string('unit', 20)->default('kg');
            $t->string('hsn_sac', 20)->nullable();
            $t->decimal('selling_price', 10, 2);
            $t->decimal('purchase_price', 10, 2)->nullable();
            $t->decimal('cgst_percent', 5, 2)->default(0);
            $t->decimal('sgst_percent', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('track_stock')->default(false);
            $t->decimal('low_stock_level', 12, 2)->default(0);
            $t->timestamps();
            $t->unique(['vendor_id', 'sku']);
            $t->index(['vendor_id', 'is_active']);
        });
        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('sequence');
            $t->string('quotation_no', 50);
            $t->date('quotation_date');
            $t->date('valid_until');
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('revision')->default(1);
            $t->json('totals');
            $t->json('party_snapshot');
            $t->text('notes')->nullable();
            $t->foreignId('invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['vendor_id', 'sequence']);
            $t->unique(['vendor_id', 'quotation_no']);
            $t->index(['vendor_id', 'customer_id', 'quotation_date']);
        });
        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $t->unsignedInteger('sr_no');
            $t->string('item_name');
            $t->string('hsn_sac', 20)->nullable();
            $t->string('unit', 20)->default('kg');
            foreach (['qty', 'rate', 'taxable_value', 'cgst_amount', 'sgst_amount', 'line_total'] as $f) {
                $t->decimal($f, 10, 2);
            }$t->decimal('cgst_percent', 5, 2);
            $t->decimal('sgst_percent', 5, 2);
        });
        Schema::create('quotation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $t->string('action', 30);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('admin_actor_id')->nullable();
            $t->json('snapshot');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->unique()->constrained('users')->restrictOnDelete();
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $t->foreignId('reverses_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->string('kind', 30);
            $t->decimal('quantity', 14, 2);
            $t->string('unit', 20);
            $t->text('reason');
            $t->unsignedBigInteger('admin_actor_id')->nullable();
            $t->timestamps();
            $t->unique(['vendor_id', 'request_key']);
            $t->index(['vendor_id', 'product_id']);
            $t->index(['vendor_id', 'invoice_id']);
        });
        Schema::create('invoice_product_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $t->foreignId('invoice_item_id')->unique()->constrained('invoice_items')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->string('unit', 20);
            $t->decimal('quantity', 10, 2);
            $t->decimal('cost_rate', 10, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->string('name', 100);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['vendor_id', 'name']);
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $t->date('expense_date');
            $t->decimal('amount', 12, 2);
            $t->string('method', 20);
            $t->string('reference', 100)->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->timestamp('voided_at')->nullable();
            $t->text('void_reason')->nullable();
            $t->timestamps();
            $t->unique(['vendor_id', 'request_key']);
            $t->index(['vendor_id', 'expense_date']);
        });
        Schema::create('expense_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->timestamps();
        });
        Schema::create('expense_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $t->string('action', 20);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('admin_actor_id')->nullable();
            $t->json('snapshot');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Business history rollback is disabled. Use a reviewed forward migration.');
    }
};
