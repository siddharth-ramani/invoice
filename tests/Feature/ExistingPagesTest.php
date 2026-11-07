<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class ExistingPagesTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $views = sys_get_temp_dir().'/bizpilot-existing-pages-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }
        config(['view.compiled' => $views]);
        BusinessFixture::createSchema();
        $this->fixture = BusinessFixture::seed();
    }

    public function test_all_existing_vendor_pages_render_in_the_shared_shell_without_inline_code(): void
    {
        $this->actingAs($this->fixture['vendor']);
        foreach (['/dashboard', '/customers', '/customers/create', '/customers/'.$this->fixture['customer']->id.'/edit', '/invoices', '/invoices/create', '/invoices/'.$this->fixture['invoice']->id, '/invoices/'.$this->fixture['invoice']->id.'/edit', '/gst', '/gst/create', '/gst/'.$this->fixture['gst']->id.'/edit', '/reports/sales', '/reports/gst', '/settings', '/subscription', '/vendor/subscription'] as $path) {
            $response = $this->get($path);
            $response->assertOk()->assertSee('id="main-content"', false)->assertSee('id="sidebarClose"', false);
            $html = $response->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)|<style\b|\sstyle=|\son(?:click|submit|change|input)=/i', $html, $path);
            $this->assertStringNotContainsString('Private Other Customer', $html, $path);
            $this->assertStringNotContainsString('@end', $html, $path);
        }
    }

    public function test_admin_pages_render_and_plan_management_is_admin_only(): void
    {
        $this->actingAs($this->fixture['admin']);
        foreach (['/dashboard', '/customers', '/customers/create', '/customers/'.$this->fixture['vendor']->id.'/edit', '/subscription-plans', '/subscription-plans/create', '/subscription-plans/'.$this->fixture['plan']->id.'/edit', '/settings'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/subscription-plans/'.$this->fixture['plan']->id)->assertRedirect(route('subscription-plans.edit', $this->fixture['plan']));
        $this->actingAs($this->fixture['vendor']);
        $this->get('/subscription-plans')->assertForbidden();
        $this->post('/subscription-plans', ['name' => 'Not allowed'])->assertForbidden();
    }

    public function test_customer_forms_persist_contact_data_and_keep_ownership(): void
    {
        $this->actingAs($this->fixture['vendor'])->post('/customers', ['name' => 'New contact', 'email' => 'new@example.test', 'mobile' => '9876543212', 'address' => 'Sample address', 'role' => 'admin'])->assertRedirect(route('customers.index'));
        $contact = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('customer', $contact->role);
        $this->assertSame($this->fixture['vendor']->id, $contact->created_by);
        $this->assertFalse(Hash::check('123456', $contact->password));
        $this->put('/customers/'.$contact->id, ['name' => 'Updated contact', 'email' => 'new@example.test', 'mobile' => '9876543212'])->assertRedirect(route('customers.index'));
        $this->assertSame('Updated contact', $contact->fresh()->name);
        $this->get('/customers/'.$this->fixture['foreignCustomer']->id.'/edit')->assertForbidden();
        $this->put('/customers/'.$this->fixture['foreignCustomer']->id, ['name' => 'Forbidden'])->assertForbidden();
        $this->from('/customers')->delete('/customers/'.$this->fixture['customer']->id)->assertSessionHas('error');
        $this->assertNotNull($this->fixture['customer']->fresh());
    }

    public function test_invoice_form_preserves_validation_input_and_server_calculations(): void
    {
        $this->actingAs($this->fixture['vendor']);
        $payload = ['invoice_no' => 'GT/002', 'invoice_date' => today()->toDateString(), 'customer_id' => $this->fixture['customer']->id, 'discount_percent' => 10, 'items' => [['sr_no' => 1, 'item_name' => 'Sample material', 'qty' => 100, 'rate' => 10, 'cgst_percent' => 9, 'sgst_percent' => 9]]];
        $this->post('/invoices', $payload)->assertRedirect(route('invoices.show', 2));
        $saved = Invoice::where('invoice_no', 'GT/002')->firstOrFail();
        $this->assertSame(1080.0, (float) $saved->grand_total);
        $this->assertSame(180.0, (float) $saved->total_tax);
        $this->assertSame(100.0, (float) $saved->discount);
        $this->get('/invoices/'.$saved->id)->assertOk()->assertSee(route('invoices.download', $saved));
        $payload['customer_id'] = $this->fixture['foreignCustomer']->id;
        $this->from('/invoices/create')->post('/invoices', $payload)->assertRedirect('/invoices/create')->assertSessionHasErrors('customer_id')->assertSessionHasInput('items.0.item_name', 'Sample material');
        $this->get('/invoices/create')->assertOk()->assertSee('Sample material');
        $this->actingAs($this->fixture['otherVendor'])->get('/invoices/'.$saved->id.'/print')->assertForbidden();
        $this->assertSame(1080.0, (float) $saved->fresh()->grand_total);
    }

    public function test_gst_settings_and_plan_forms_save_valid_data_and_show_errors(): void
    {
        $this->actingAs($this->fixture['vendor'])->post('/gst', ['name' => 'GST 5%', 'rate' => 5])->assertRedirect(route('gst.index'));
        $this->assertSame(2, DB::table('gst_slabs')->where('user_id', $this->fixture['vendor']->id)->count());
        $this->post('/settings', ['name' => 'Updated vendor', 'email' => 'vendor@example.test', 'company_name' => 'Updated company', 'invoice_prefix' => 'PT', 'terms_conditions' => 'Saved terms'])->assertRedirect();
        $this->assertSame('Updated company', $this->fixture['vendor']->fresh()->company_name);
        $this->from('/gst/create')->post('/gst', ['name' => 'Bad', 'rate' => 101])->assertSessionHasErrors('rate');
        $this->get('/gst/create')->assertOk()->assertSee('rate-error', false);
        $this->actingAs($this->fixture['admin'])->post('/subscription-plans', ['name' => 'New plan', 'price' => 199, 'duration_days' => 30, 'invoice_limit' => 50, 'status' => 1])->assertRedirect(route('subscription-plans.index'));
        $this->assertTrue(DB::table('subscription_plans')->where('name', 'New plan')->exists());
        $this->from('/subscription-plans')->delete('/subscription-plans/'.$this->fixture['plan']->id)->assertSessionHas('error');
    }

    public function test_reports_filter_validate_and_keep_invoice_snapshots_unchanged(): void
    {
        $before = DB::table('invoices')->get()->toJson();
        $this->actingAs($this->fixture['vendor']);
        $this->get('/reports/sales?from_date='.today()->toDateString().'&to_date='.today()->toDateString())->assertOk()->assertSee('GT/001')->assertSee('₹1,180.00');
        $this->from('/reports/gst')->get('/reports/gst?from_date[]=invalid')->assertSessionHasErrors('from_date');
        $this->get('/reports/gst')->assertOk();
        $this->assertSame($before, DB::table('invoices')->get()->toJson());
    }

    public function test_login_screen_and_real_session_login_use_existing_authentication(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-password-toggle', false);
        $this->post('/login', ['email' => 'vendor@example.test', 'password' => 'Synthetic-Password-42'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->fixture['vendor']);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_empty_modules_remain_usable_without_placeholder_rows(): void
    {
        $emptyVendor = User::forceCreate(['name' => 'Empty vendor', 'role' => 'vendor', 'password' => 'Synthetic-Password-42']);
        $this->actingAs($emptyVendor);
        foreach (['/customers' => 'No customers yet', '/gst' => 'No GST rates saved', '/invoices' => 'No invoices found', '/reports/sales' => 'No invoices in this period', '/vendor/subscription' => 'No active subscription'] as $path => $message) {
            $this->get($path)->assertOk()->assertSee($message);
        }
        $this->get('/invoices/create')->assertRedirect(route('subscription'));
    }

    public function test_invalid_plan_arrays_render_errors_and_admin_can_set_vendor_credentials(): void
    {
        $this->actingAs($this->fixture['admin'])->from('/subscription-plans/create')->post('/subscription-plans', ['name' => 'Invalid plan', 'price' => 199, 'duration_days' => 30, 'status' => ['bad']])->assertSessionHasErrors('status');
        $this->get('/subscription-plans/create')->assertOk()->assertSee('status-error', false);
        $this->post('/customers', ['name' => 'New vendor', 'email' => 'newvendor@example.test', 'password' => 'New-Vendor-Pass-42', 'password_confirmation' => 'New-Vendor-Pass-42'])->assertRedirect(route('customers.index'));
        $vendor = User::where('email', 'newvendor@example.test')->firstOrFail();
        $this->assertSame('vendor', $vendor->role);
        $this->assertTrue(Hash::check('New-Vendor-Pass-42', $vendor->password));
    }

    public function test_failed_login_retains_email_without_showing_the_password(): void
    {
        $this->from('/login')->post('/login', ['email' => 'vendor@example.test', 'password' => 'Wrong-Synthetic-Password'])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()->assertSee('vendor@example.test')->assertDontSee('Wrong-Synthetic-Password');
    }

    public function test_bulk_pdf_downloads_use_distinct_files_and_safe_archive_names(): void
    {
        $this->actingAs($this->fixture['vendor']);
        $query = http_build_query(['from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]);
        $paths = [];
        try {
            foreach ([1, 2] as $attempt) {
                $response = $this->get('/invoices/bulk-download?'.$query)->assertOk();
                $path = $response->baseResponse->getFile()->getPathname();
                $paths[] = $path;
                $zip = new \ZipArchive;
                $this->assertTrue($zip->open($path) === true);
                $this->assertSame(1, $zip->numFiles);
                $this->assertSame('Invoice_GT-001.pdf', $zip->getNameIndex(0));
                $this->assertStringStartsWith('%PDF-', $zip->getFromIndex(0));
                $zip->close();
            }
            $this->assertNotSame($paths[0], $paths[1]);
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_landing_has_role_correct_workspace_links_without_exposing_business_records_or_writing_data(): void
    {
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|create|alter|drop)/i', $q->sql)) {
                $writes[] = $q->sql;
            }
        });
        foreach (['vendor', 'admin', 'customer'] as $role) {
            $this->actingAs($this->fixture[$role]);
            $r = $this->get('/?lang=en')->assertOk()->assertSee(route($role === 'customer' ? 'settings' : 'dashboard'));
            $r->assertDontSee($this->fixture['foreignCustomer']->email)->assertDontSee($this->fixture['vendor']->email);
        }
        $this->assertSame([], $writes);
    }
}
