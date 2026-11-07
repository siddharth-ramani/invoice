<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // vendor who owns this slab
            $table->string('name'); // slab name, e.g., "5%, 12%, 18%"
            $table->decimal('rate', 5, 2); // GST rate in %
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_slabs');
    }
};
