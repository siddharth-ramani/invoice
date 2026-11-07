<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never migrate/reset the configured business database. This suite owns an in-memory connection.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        $views = sys_get_temp_dir().'/bizpilot-dashboard-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }
        config(['view.compiled' => $views]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role');
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
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function account(string $role = 'vendor', ?int $owner = null, string $name = 'Sample account'): User
    {
        return User::forceCreate(['name' => $name, 'role' => $role, 'created_by' => $owner, 'password' => 'test-only-password']);
    }

    private function invoice(User $vendor, User $customer, string $number, string $date, float $amount, array $extra = []): Invoice
    {
        return Invoice::forceCreate(array_merge([
            'vendor_id' => $vendor->id, 'customer_id' => $customer->id, 'invoice_no' => $number,
            'invoice_date' => $date, 'grand_total' => $amount, 'total_tax' => 18,
        ], $extra));
    }

    public function test_vendor_dashboard_filters_stored_totals_and_never_changes_invoices(): void
    {
        $vendor = $this->account();
        $customer = $this->account('customer', $vendor->id, 'Local customer');
        $other = $this->account();
        $foreignCustomer = $this->account('customer', $other->id, 'Private foreign customer');
        $this->invoice($vendor, $customer, 'FIRST', '2026-01-01', 118);
        $this->invoice($vendor, $customer, 'LAST', '2026-01-31', 236, ['status' => 'cancelled']);
        $this->invoice($vendor, $customer, 'OUTSIDE', '2026-02-01', 999);
        $this->invoice($other, $foreignCustomer, 'PRIVATE-NUMBER', '2026-01-15', 9000);
        $this->invoice($vendor, $customer, 'DELETED', '2026-01-15', 800, ['deleted_at' => now()]);
        $before = DB::table('invoices')->orderBy('id')->get()->toJson();

        $response = $this->actingAs($vendor)->get('/dashboard?from_date=2026-01-01&to_date=2026-01-31');
        $response->assertOk()->assertViewHas('summary', fn ($s) => (float) $s->sales === 354.0 && (int) $s->invoice_count === 2 && (float) $s->tax === 36.0 && (int) $s->customer_count === 1)
            ->assertSee('FIRST')->assertSee('LAST')->assertDontSee('PRIVATE-NUMBER')->assertDontSee('Private foreign customer')->assertDontSee('DELETED')->assertDontSee('OUTSIDE')
            ->assertViewHas('months', fn ($months) => count($months) === 1 && $months[0]['sales'] === 354.0);
        $this->assertSame($before, DB::table('invoices')->orderBy('id')->get()->toJson());
    }

    public function test_admin_sees_all_businesses_but_no_vendor_only_invoice_actions(): void
    {
        $admin = $this->account('admin');
        $a = $this->account();
        $b = $this->account();
        $customer = $this->account('customer', $a->id);
        $this->invoice($a, $customer, 'ADMIN-A', '2026-01-01', 100);
        $this->invoice($b, $customer, 'ADMIN-B', '2026-01-02', 200);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('All businesses')
            ->assertSee('ADMIN-A')->assertSee('ADMIN-B')->assertDontSee('Create invoice')
            ->assertDontSee('Download invoice ADMIN-A PDF')
            ->assertViewHas('summary', fn ($s) => (float) $s->sales === 300.0)
            ->assertViewHas('accounts', fn ($a) => (int) $a->vendors === 2 && (int) $a->customers === 1);
    }

    public function test_empty_dashboard_has_honest_empty_states_and_zero_filled_months(): void
    {
        $this->actingAs($this->account())->get('/dashboard?from_date=2026-01-01&to_date=2026-03-31')
            ->assertOk()->assertSee('No invoices in this period')->assertSee('Payment records needed')
            ->assertSee('Expense and cost records needed')->assertSee('₹0.00')
            ->assertViewHas('months', fn ($m) => count($m) === 3 && array_sum(array_column($m, 'sales')) === 0.0);
    }

    public function test_invalid_reversed_and_excessive_date_ranges_return_validation_errors(): void
    {
        $this->actingAs($this->account());
        foreach ([
            ['from_date=bad&to_date=2026-01-31', 'from_date'],
            ['from_date=2026-02-30&to_date=2026-03-31', 'from_date'],
            ['from_date=2026-02-01&to_date=2026-01-01', 'to_date'],
            ['from_date=2020-01-01&to_date=2026-01-01', 'to_date'],
            ['from_date[]=2026-01-01', 'from_date'],
        ] as [$query, $field]) {
            $this->from('/dashboard')->get('/dashboard?'.$query)->assertRedirect('/dashboard')->assertSessionHasErrors($field);
        }
    }

    public function test_guest_redirects_and_customer_cannot_view_business_analytics(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($this->account('customer'))->get('/dashboard')->assertForbidden();
    }

    public function test_rankings_recent_limit_zero_months_and_escaped_names(): void
    {
        $vendor = $this->account();
        $a = $this->account('customer', $vendor->id, '<script>alert(1)</script>');
        $b = $this->account('customer', $vendor->id, 'Best customer');
        for ($i = 1; $i <= 8; $i++) {
            $this->invoice($vendor, $a, 'SAMPLE-'.$i, '2026-01-'.str_pad($i, 2, '0', STR_PAD_LEFT), 10);
        }
        $this->invoice($vendor, $b, 'BEST', '2026-03-01', 1000);
        $this->actingAs($vendor)->get('/dashboard?from_date=2026-01-01&to_date=2026-03-31')->assertOk()
            ->assertViewHas('topCustomers', fn ($c) => $c->first()->customer_id === $b->id)
            ->assertViewHas('recentInvoices', fn ($i) => $i->count() === 6 && $i->first()->invoice_no === 'BEST')
            ->assertViewHas('months', fn ($m) => $m[1]['sales'] === 0.0)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('https://cdn')->assertDontSee('<style', false);
    }

    public function test_default_period_uses_the_business_calendar_and_filters_may_reset(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-31 20:00:00', 'UTC'));
        $this->actingAs($this->account())->get('/dashboard')->assertOk()
            ->assertViewHas('from', fn ($d) => $d->toDateString() === '2027-01-01')
            ->assertViewHas('to', fn ($d) => $d->toDateString() === '2027-01-01');
    }

    public function test_invalid_array_filters_can_return_to_a_rendered_dashboard_safely(): void
    {
        $this->actingAs($this->account())->from('/dashboard')->get('/dashboard?from_date[]=2026-01-01&to_date[]=2026-01-31')
            ->assertRedirect('/dashboard')->assertSessionHasErrors(['from_date', 'to_date']);
        $this->get('/dashboard')->assertOk()->assertSee('from_date-error', false)->assertSee('to_date-error', false);
    }

    public function test_dashboard_query_count_does_not_grow_with_invoice_count(): void
    {
        $vendor = $this->account();
        $customer = $this->account('customer', $vendor->id);
        for ($i = 0; $i < 20; $i++) {
            $this->invoice($vendor, $customer, 'PERF-'.$i, '2026-01-01', 100);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($vendor)->get('/dashboard')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(6, count($queries), 'Dashboard should use bounded aggregates and eager loading.');
    }

    public function test_recent_invoice_pdf_action_generates_a_pdf_without_changing_totals(): void
    {
        $vendor = $this->account();
        $vendor->forceFill(['company_name' => 'Sample business', 'address' => 'Sample address', 'terms_conditions' => 'Sample terms'])->save();
        $customer = $this->account('customer', $vendor->id);
        $invoice = $this->invoice($vendor, $customer, 'PDF/001', '2026-01-01', 118, ['taxable_amount' => 100, 'cgst_total' => 9, 'sgst_total' => 9]);
        $invoice->items()->create(['sr_no' => 1, 'item_name' => 'Sample service', 'qty' => 1, 'rate' => 100, 'taxable_value' => 100, 'cgst_percent' => 9, 'cgst_amount' => 9, 'sgst_percent' => 9, 'sgst_amount' => 9, 'line_total' => 118]);
        $before = DB::table('invoices')->get()->toJson();
        $this->actingAs($vendor)->get('/dashboard')->assertOk()->assertSee(route('invoices.download', $invoice->id));
        $pdf = $this->get(route('invoices.download', $invoice->id));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame($before, DB::table('invoices')->get()->toJson());
    }
}
