<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');

            $table->integer('sr_no');

            $table->string('item_name');
            $table->string('hsn_sac')->nullable();

            $table->decimal('qty', 10, 2);
            $table->decimal('rate', 10, 2);

            $table->decimal('taxable_value', 10, 2);

            $table->decimal('cgst_percent', 5, 2)->default(0);
            $table->decimal('cgst_amount', 10, 2)->default(0);

            $table->decimal('sgst_percent', 5, 2)->default(0);
            $table->decimal('sgst_amount', 10, 2)->default(0);

            $table->decimal('line_total', 10, 2);

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
