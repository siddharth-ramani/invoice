<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('customer_id');

            // Invoice meta (PDF)
            $table->string('invoice_no'); // MANUAL ENTRY
            $table->date('invoice_date');
            $table->string('challan_no')->nullable();
            $table->date('challan_date')->nullable();

            // Supply info
            $table->string('place_of_supply')->nullable();

            // Totals (snapshot)
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            // Other
            $table->string('amount_in_words')->nullable();
            $table->enum('status', ['unpaid','paid','cancelled'])->default('unpaid');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes (important)
            $table->index('vendor_id');
            $table->index('customer_id');
            $table->index('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
