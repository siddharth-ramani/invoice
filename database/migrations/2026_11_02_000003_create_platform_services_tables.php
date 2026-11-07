<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $t->string('locale', 5)->default('en');
            $t->boolean('reminders_enabled')->default(false);
            $t->boolean('email_enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('platform_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('account_controls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $t->boolean('disabled')->default(false);
            $t->text('reason');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('admin_actor_id')->nullable();
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('action', 80);
            $t->string('target_type', 80);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->json('details');
            $t->timestamps();
            $t->index(['vendor_id', 'created_at']);
        });
        Schema::create('plan_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $t->string('feature', 50);
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('limit_value')->nullable();
            $t->timestamps();
            $t->unique(['subscription_plan_id', 'feature']);
        });
        Schema::create('subscription_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('snapshot');
            $t->text('reason');
            $t->uuid('request_key')->nullable();
            $t->char('request_hash', 64)->nullable();
            $t->timestamps();
            $t->unique(['vendor_id', 'request_key']);
        });
        Schema::create('billing_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $t->string('plan_name');
            $t->date('payment_date');
            $t->decimal('amount', 12, 2);
            $t->string('reference', 100);
            $t->string('kind', 20)->default('received');
            $t->foreignId('reverses_id')->nullable()->unique()->constrained('billing_records')->restrictOnDelete();
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->text('reason');
            $t->timestamps();
            $t->unique(['vendor_id', 'request_key']);
            $t->index('payment_date');
        });
        Schema::create('ai_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->string('status', 20);
            $t->string('kind', 20);
            $t->string('locale', 5);
            $t->text('question');
            $t->longText('answer')->nullable();
            $t->unsignedInteger('credits')->default(1);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('estimated_cost', 12, 6)->nullable();
            $t->string('model', 100);
            $t->string('error_code', 50)->nullable();
            $t->timestamps();
            $t->unique(['vendor_id', 'request_key']);
            $t->index(['vendor_id', 'created_at']);
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $t->string('subject', 150);
            $t->string('status', 20)->default('open');
            $t->timestamps();
            $t->index(['vendor_id', 'status']);
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_ticket_id')->constrained('support_tickets')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('business_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('event_key', 150);
            $t->string('title', 150);
            $t->text('body');
            $t->json('parameters')->nullable();
            $t->string('target', 255);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'event_key']);
            $t->index(['user_id', 'read_at']);
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_notification_id')->unique()->constrained('business_notifications')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->text('recipient');
            $t->string('status', 20)->default('queued');
            $t->string('error_code', 50)->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Platform history rollback is disabled. Use a reviewed forward migration.');
    }
};
