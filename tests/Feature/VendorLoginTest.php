<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class VendorLoginTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $views = sys_get_temp_dir().'/bizpilot-vendor-login-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }
        config(['view.compiled' => $views]);
        BusinessFixture::createSchema();
        $this->fixture = BusinessFixture::seed();
    }

    public function test_admin_can_enter_vendor_context_and_return_without_changing_passwords_or_invoices(): void
    {
        $beforeUsers = DB::table('users')->orderBy('id')->get()->toJson();
        $beforeInvoices = DB::table('invoices')->get()->toJson();
        $this->actingAs($this->fixture['admin']);
        $this->get('/customers')->assertOk()->assertSee('Login as vendor');
        $this->withSession(['unrelated_admin_state' => 'clear on switch']);
        $oldSession = session()->getId();
        $this->post(route('admin.vendors.login', $this->fixture['vendor']), ['admin_id' => $this->fixture['otherVendor']->id])->assertRedirect(route('dashboard'))
            ->assertSessionHas('bizpilot.impersonation.admin_id', $this->fixture['admin']->id)
            ->assertSessionHas('bizpilot.impersonation.vendor_id', $this->fixture['vendor']->id)
            ->assertSessionMissing('unrelated_admin_state');
        $this->assertAuthenticatedAs($this->fixture['vendor']);
        $this->assertNotSame($oldSession, session()->getId());
        $this->get('/dashboard')->assertOk()->assertSee('Return to admin')->assertSee('Actions use this vendor');
        $this->get('/customers')->assertOk()->assertSee('Shree Enterprise')->assertDontSee('Private Other Customer')->assertDontSee('Login as vendor');
        $this->get('/subscription-plans')->assertForbidden();
        $this->post(route('admin.vendors.login', $this->fixture['otherVendor']))->assertForbidden();
        $vendorSession = session()->getId();
        $this->post(route('admin.vendors.return'))->assertRedirect(route('customers.index'))->assertSessionMissing('bizpilot.impersonation');
        $this->assertAuthenticatedAs($this->fixture['admin']);
        $this->assertNotSame($vendorSession, session()->getId());
        $this->get('/customers')->assertOk()->assertDontSee('Return to admin');
        $this->assertSame($beforeUsers, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeInvoices, DB::table('invoices')->get()->toJson());
    }

    public function test_only_admins_can_start_and_only_vendors_can_be_targets(): void
    {
        $this->post(route('admin.vendors.login', $this->fixture['vendor']))->assertRedirect(route('login'));
        foreach (['vendor', 'customer'] as $role) {
            $this->actingAs($this->fixture[$role])->post(route('admin.vendors.login', $this->fixture['otherVendor']))->assertForbidden();
            $this->post(route('admin.vendors.return'), ['admin_id' => $this->fixture['admin']->id])->assertForbidden();
        }
        $this->actingAs($this->fixture['admin']);
        $this->post(route('admin.vendors.login', $this->fixture['admin']))->assertForbidden();
        $this->post(route('admin.vendors.login', $this->fixture['customer']))->assertForbidden();
        $this->post('/admin/vendors/999999/login')->assertNotFound();
        $this->get(route('admin.vendors.login', $this->fixture['vendor']))->assertStatus(405);
    }

    public function test_revoking_original_admin_role_ends_vendor_access(): void
    {
        $this->actingAs($this->fixture['admin'])->post(route('admin.vendors.login', $this->fixture['vendor']))->assertRedirect();
        $this->fixture['admin']->forceFill(['role' => 'customer'])->save();
        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionMissing('bizpilot.impersonation');
        $this->assertGuest();
    }

    public function test_context_cannot_be_reused_for_a_different_vendor_and_return_rejects_deleted_admin(): void
    {
        $context = ['admin_id' => $this->fixture['admin']->id, 'vendor_id' => $this->fixture['vendor']->id];
        $this->actingAs($this->fixture['otherVendor'])->withSession(['bizpilot.impersonation' => $context])->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($this->fixture['vendor'])->withSession(['bizpilot.impersonation' => $context]);
        $this->fixture['admin']->delete();
        $this->post(route('admin.vendors.return'))->assertRedirect(route('login'))->assertSessionMissing('bizpilot.impersonation');
        $this->assertGuest();
    }

    public function test_signing_out_as_vendor_ends_the_whole_session(): void
    {
        $this->actingAs($this->fixture['admin'])->post(route('admin.vendors.login', $this->fixture['vendor']))->assertRedirect();
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('bizpilot.impersonation');
        $this->assertGuest();
        $this->post(route('admin.vendors.return'))->assertRedirect(route('login'));
    }

    public function test_switch_requires_a_valid_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->actingAs($this->fixture['admin'])->post(route('admin.vendors.login', $this->fixture['vendor']))->assertStatus(419);
            $this->assertAuthenticatedAs($this->fixture['admin']);
            $token = str_repeat('a', 40);
            $this->withSession(['_token' => $token])->post(route('admin.vendors.login', $this->fixture['vendor']), ['_token' => $token])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($this->fixture['vendor']);
            $this->post(route('admin.vendors.return'), ['_token' => $token])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
