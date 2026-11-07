<?php

// database/migrations/xxxx_xx_xx_create_subscriptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // vendor
            $table->unsignedBigInteger('subscription_plan_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('invoice_used')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->foreign('subscription_plan_id')
                  ->references('id')->on('subscription_plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
