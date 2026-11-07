<?php

namespace Tests\Support;

use App\Models\GstSlab;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessFixture
{
    public static function createSchema(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \LogicException('Synthetic fixtures require an isolated SQLite connection.');
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role');
            $table->string('invoice_prefix')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            foreach (['company_name', 'address', 'mobile', 'mobile_2', 'gst_number', 'terms_conditions'] as $field) {
                $table->string($field)->nullable();
            }
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('invoice_no');
            $table->date('invoice_date');
            $table->decimal('grand_total', 12, 2);
            $table->decimal('total_tax', 12, 2)->default(0);
            foreach (['taxable_amount', 'cgst_total', 'sgst_total', 'discount', 'discount_percent', 'round_off', 'total_before_round'] as $field) {
                $table->decimal($field, 12, 2)->default(0);
            }
            $table->text('notes')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->integer('sr_no');
            $table->string('item_name');
            $table->string('hsn_sac')->nullable();
            foreach (['qty', 'rate', 'taxable_value', 'cgst_percent', 'cgst_amount', 'sgst_percent', 'sgst_amount', 'line_total'] as $field) {
                $table->decimal($field, 12, 2)->default(0);
            }
            $table->timestamps();
        });
        Schema::create('gst_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
        });
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('duration_days');
            $table->integer('invoice_limit')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_plan_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('invoice_used')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    public static function seed(): array
    {
        $admin = User::forceCreate(['name' => 'Platform Administrator', 'email' => 'admin@example.test', 'role' => 'admin', 'password' => 'Synthetic-Password-42']);
        $vendor = User::forceCreate(['name' => 'Rohan Patel', 'company_name' => 'Patel Trading Co.', 'email' => 'vendor@example.test', 'role' => 'vendor', 'password' => 'Synthetic-Password-42', 'created_by' => $admin->id, 'invoice_prefix' => 'GT', 'address' => 'Sample business address, Ahmedabad', 'mobile' => '9876543210', 'mobile_2' => '', 'terms_conditions' => 'Payment due as agreed.', 'gst_number' => '24ABCDE1234F1Z5']);
        $otherVendor = User::forceCreate(['name' => 'Other Business', 'email' => 'other@example.test', 'role' => 'vendor', 'password' => 'Synthetic-Password-42', 'created_by' => $admin->id]);
        $customer = User::forceCreate(['name' => 'Shree Enterprise', 'email' => 'shree@example.test', 'mobile' => '9876543211', 'address' => 'Sample customer address, Surat', 'gst_number' => '24FGHIJ1234K1Z5', 'role' => 'customer', 'created_by' => $vendor->id, 'password' => 'Synthetic-Password-42']);
        $foreignCustomer = User::forceCreate(['name' => 'Private Other Customer', 'email' => 'private@example.test', 'role' => 'customer', 'created_by' => $otherVendor->id, 'password' => 'Synthetic-Password-42']);
        $plan = SubscriptionPlan::create(['name' => 'Starter', 'price' => 249, 'duration_days' => 30, 'invoice_limit' => 100, 'status' => 1]);
        $secondPlan = SubscriptionPlan::create(['name' => 'Business', 'price' => 899, 'duration_days' => 365, 'invoice_limit' => null, 'status' => 1]);
        $subscription = Subscription::create(['user_id' => $vendor->id, 'subscription_plan_id' => $plan->id, 'start_date' => today(), 'end_date' => today()->addDays(30), 'invoice_used' => 1, 'is_active' => 1]);
        $gst = GstSlab::create(['user_id' => $vendor->id, 'name' => 'GST 18%', 'rate' => 18]);
        $invoice = Invoice::create(['vendor_id' => $vendor->id, 'customer_id' => $customer->id, 'invoice_no' => 'GT/001', 'invoice_date' => today()->toDateString(), 'taxable_amount' => 1000, 'cgst_total' => 90, 'sgst_total' => 90, 'total_tax' => 180, 'discount' => 0, 'discount_percent' => 0, 'round_off' => 0, 'total_before_round' => 1180, 'grand_total' => 1180, 'notes' => 'Thank you for your business.']);
        $invoice->items()->create(['sr_no' => 1, 'item_name' => 'Cotton yarn', 'hsn_sac' => '5205', 'qty' => 100, 'rate' => 10, 'taxable_value' => 1000, 'cgst_percent' => 9, 'cgst_amount' => 90, 'sgst_percent' => 9, 'sgst_amount' => 90, 'line_total' => 1180]);

        return compact('admin', 'vendor', 'otherVendor', 'customer', 'foreignCustomer', 'plan', 'secondPlan', 'subscription', 'gst', 'invoice');
    }
}
