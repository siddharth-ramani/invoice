<?php

namespace Tests\Feature;

use App\Models\AccountControl;
use App\Models\BillingRecord;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'bizpilot.platform_enabled' => true, 'bizpilot.business_modules_enabled' => true, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $dir = sys_get_temp_dir().'/bizpilot-registration-test-views';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }config(['view.compiled' => $dir]);
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::fake();
        BusinessFixture::createSchema();
        $this->f = BusinessFixture::seed();
        foreach (['2026_11_02_000001_create_payment_ledger_tables', '2026_11_02_000002_create_business_modules_tables', '2026_11_02_000003_create_platform_services_tables'] as $m) {
            (require database_path('migrations/'.$m.'.php'))->up();
        }
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'New business owner', 'company_name' => 'New Example Business', 'email' => 'new.owner@example.test', 'mobile' => '9876543210', 'password' => 'New-Password-42', 'password_confirmation' => 'New-Password-42', 'locale' => 'en'], $extra);
    }

    public function test_public_pages_and_links_are_translated_and_registration_is_guest_only(): void
    {
        foreach (['en', 'gu', 'hi'] as $lang) {
            $this->get('/login?lang='.$lang)->assertOk()->assertSee(route('register', ['lang' => $lang]));
            $this->get('/register?lang='.$lang)->assertOk()->assertSee('lang="'.$lang.'"', false)->assertSee('password_confirmation', false)->assertSee('auth-setup-box', false);
        }
        $this->get('/?lang=en')->assertOk()->assertSee(route('register', ['lang' => 'en']));
        $this->actingAs($this->f['vendor']);
        $this->get('/register')->assertRedirect(route('dashboard'));
        $this->post('/register', $this->payload())->assertRedirect(route('dashboard'));
        $this->assertSame(5, User::count());
    }

    public function test_registration_onboarding_logout_and_login_keep_existing_records_and_roles_safe(): void
    {
        $old = $this->f['invoice']->fresh()->getAttributes();
        $oldPassword = $this->f['vendor']->fresh()->password;
        $this->post('/register', $this->payload(['email' => ' NEW.OWNER@example.test ', 'role' => 'admin', 'created_by' => 1, 'invoice_prefix' => 'BAD', 'subscription_plan_id' => 1, 'invoice_used' => 0, 'locale' => 'gu']))->assertSessionHasNoErrors()->assertRedirect(route('business.setup'));
        $user = User::where('email', 'new.owner@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('vendor', $user->role);
        $this->assertNull($user->created_by);
        $this->assertSame('INV', $user->invoice_prefix);
        $this->assertSame('New Example Business', $user->company_name);
        $this->assertTrue(Hash::check('New-Password-42', $user->password));
        $this->assertNotSame('New-Password-42', $user->password);
        $this->assertSame('gu', UserPreference::where('user_id', $user->id)->value('locale'));
        $this->assertSame(1, Subscription::count());
        $this->assertSame(0, BillingRecord::count());
        $this->get('/business-setup')->assertOk();
        $this->get('/customers')->assertOk()->assertViewHas('customers', fn ($p) => $p->total() === 0);
        $this->get('/invoices/1')->assertForbidden();
        $this->get('/admin/platform')->assertForbidden();
        $this->get('/invoices/create')->assertRedirect(route('subscription'));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'NEW.OWNER@EXAMPLE.TEST', 'password' => 'New-Password-42'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($old, $this->f['invoice']->fresh()->getAttributes());
        $this->assertSame($oldPassword, $this->f['vendor']->fresh()->password);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_duplicate_and_malformed_registration_does_not_overwrite_accounts_or_flash_passwords(): void
    {
        $password = $this->f['vendor']->fresh()->password;
        $this->from('/register')->post('/register', $this->payload(['email' => 'VENDOR@example.test']))->assertSessionHasErrors('email');
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
        $this->assertSame($password, $this->f['vendor']->fresh()->password);
        $this->assertSame(5, User::count());
        $this->post('/register', $this->payload(['password' => ['bad'], 'password_confirmation' => ['bad']]))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password' => str_repeat('a', 73).'1', 'password_confirmation' => str_repeat('a', 73).'1']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->assertSame(5, User::count());
    }

    public function test_registration_is_csrf_protected_and_rotates_the_session_token(): void
    {
        $this->app->instance('env', 'local');
        try {
            $token = str_repeat('b', 40);
            $this->withSession(['_token' => $token])->post('/register', $this->payload())->assertStatus(419);
            $this->assertSame(5, User::count());
            $this->withSession(['_token' => $token])->post('/register', $this->payload(['_token' => $token]))->assertRedirect(route('business.setup'));
            $this->assertNotSame($token, session()->token());
            $this->post('/logout', ['_token' => $token])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_registration_throttles_without_creating_accounts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }
        $this->post('/register', $this->payload())->assertStatus(429);
        $this->assertSame(5, User::count());
    }

    public function test_existing_customer_and_disabled_account_login_are_handled_correctly(): void
    {
        $this->post('/login', ['email' => 'SHREE@EXAMPLE.TEST', 'password' => 'Synthetic-Password-42'])->assertRedirect(route('settings'));
        $this->get('/login')->assertRedirect(route('settings'));
        $this->get('/register')->assertRedirect(route('settings'));
        $this->get('/dashboard')->assertForbidden();
        $this->post('/logout');
        AccountControl::create(['user_id' => 2, 'disabled' => true, 'actor_id' => 1, 'reason' => 'Synthetic disabled account']);
        $this->post('/login', ['email' => 'vendor@example.test', 'password' => 'Synthetic-Password-42'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_new_business_can_request_a_plan_and_admin_activation_does_not_touch_old_usage(): void
    {
        $old = $this->f['subscription']->fresh()->getAttributes();
        $this->post('/register', $this->payload())->assertSessionHasNoErrors();
        $user = auth()->user();
        $this->post(route('subscription.subscribe', $this->f['plan']->id))->assertSessionHasNoErrors();
        $ticket = SupportTicket::where('vendor_id', $user->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame(1, Subscription::count());
        $this->actingAs($this->f['admin']);
        $this->post('/admin/platform/vendors/'.$user->id.'/subscription', ['subscription_plan_id' => $this->f['plan']->id, 'mode' => 'replace', 'expected_active_id' => null, 'expected_end_date' => null, 'request_key' => Str::uuid()->toString(), 'reason' => 'Synthetic account plan approval', 'confirm' => 1, 'billing_amount' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->where('is_active', true)->count());
        $this->assertSame(0, BillingRecord::count());
        $this->assertSame($old, $this->f['subscription']->fresh()->getAttributes());
        $this->actingAs($user);
        $this->get('/invoices/create')->assertOk();
    }

    public function test_password_change_requires_current_password_and_does_not_promote_the_account(): void
    {
        $this->post('/register', $this->payload())->assertSessionHasNoErrors();
        $user = auth()->user();
        $data = ['name' => $user->name, 'email' => $user->email, 'company_name' => $user->company_name, 'invoice_prefix' => 'INV', 'role' => 'admin', 'password' => 'Changed-Password-84', 'password_confirmation' => 'Changed-Password-84'];
        $this->post('/settings', $data)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('New-Password-42', $user->fresh()->password));
        $this->post('/settings', array_replace($data, ['current_password' => 'New-Password-42']))->assertSessionHasNoErrors();
        $this->assertSame('vendor', $user->fresh()->role);
        $this->assertTrue(Hash::check('Changed-Password-84', $user->fresh()->password));
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'New-Password-42'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'Changed-Password-84'])->assertRedirect(route('dashboard'));
    }

    public function test_login_throttle_and_auth_page_cache_headers(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/register')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'vendor@example.test', 'password' => 'Synthetic-Wrong-42'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'vendor@example.test', 'password' => 'Synthetic-Password-42'])->assertStatus(429);
        $this->assertGuest();
    }
}
