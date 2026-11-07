<?php

namespace Tests\Feature;

use App\Mail\BusinessUpdate;
use App\Models\AccountControl;
use App\Models\BillingRecord;
use App\Models\BusinessNotification;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\UserPreference;
use App\Services\Business\BusinessSetup;
use App\Services\Business\EmailOutbox;
use App\Services\Business\PlanCatalog;
use App\Services\Business\Platform;
use App\Services\Documents\UnicodePdf;
use App\Services\Invoicing\InvoicePdf;
use App\Services\Payments\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'bizpilot.platform_enabled' => true, 'bizpilot.business_modules_enabled' => true, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $dir = sys_get_temp_dir().'/bizpilot-platform-test-views';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }config(['view.compiled' => $dir]);
        Storage::fake('local');
        Http::preventStrayRequests();
        BusinessFixture::createSchema();
        $this->f = BusinessFixture::seed();
        foreach (['2026_11_02_000001_create_payment_ledger_tables', '2026_11_02_000002_create_business_modules_tables', '2026_11_02_000003_create_platform_services_tables'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }$this->actingAs($this->f['vendor']);
    }

    public function test_admin_pages_are_forbidden_to_vendors_and_customers(): void
    {
        foreach (['/admin/platform', '/admin/platform/vendors/2', '/admin/platform/entitlements', '/admin/platform/integrations', '/admin/platform/audit'] as $path) {
            $this->get($path)->assertForbidden();
        }$this->actingAs($this->f['customer']);
        $this->get('/ai')->assertNotFound();
        $this->get('/support')->assertForbidden();
    }

    public function test_vendor_plan_request_does_not_activate_or_reset_usage(): void
    {
        $before = $this->f['subscription']->fresh()->getAttributes();
        $this->post('/subscription/subscribe/'.$this->f['secondPlan']->id)->assertSessionHasNoErrors();
        $this->post('/subscription/subscribe/'.$this->f['secondPlan']->id)->assertSessionHasNoErrors();
        $this->assertSame($before, $this->f['subscription']->fresh()->getAttributes());
        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(0, BillingRecord::count());
    }

    public function test_manual_extension_is_versioned_idempotent_and_not_fake_revenue(): void
    {
        $this->actingAs($this->f['admin']);
        $s = $this->f['subscription']->fresh();
        $data = ['subscription_plan_id' => $s->subscription_plan_id, 'mode' => 'extend', 'expected_active_id' => $s->id, 'expected_end_date' => Carbon::parse($s->end_date)->toDateString(), 'request_key' => Str::uuid()->toString(), 'reason' => 'Approved manual extension', 'confirm' => '1'];
        $this->post('/admin/platform/vendors/2/subscription', $data)->assertSessionHasNoErrors();
        $newEnd = $s->fresh()->end_date;
        $this->post('/admin/platform/vendors/2/subscription', $data)->assertSessionHasNoErrors();
        $this->assertSame($newEnd, $s->fresh()->end_date);
        $this->assertSame(1, $s->fresh()->invoice_used);
        $this->assertSame(0, BillingRecord::count());
        $this->assertSame(1, DB::table('subscription_events')->count());
        $data['request_key'] = Str::uuid()->toString();
        $this->post('/admin/platform/vendors/2/subscription', $data)->assertSessionHasErrors('subscription');
    }

    public function test_actual_manual_billing_and_controlled_reversal_preserve_original_receipt(): void
    {
        $this->actingAs($this->f['admin']);
        $s = $this->f['subscription']->fresh();
        $data = ['subscription_plan_id' => $this->f['secondPlan']->id, 'mode' => 'replace', 'expected_active_id' => $s->id, 'expected_end_date' => Carbon::parse($s->end_date)->toDateString(), 'request_key' => Str::uuid()->toString(), 'reason' => 'New manually approved period', 'confirm' => '1', 'billing_amount' => '899.25', 'payment_date' => today()->toDateString(), 'reference' => 'MANUAL-ACTUAL-RECEIPT', 'confirm_payment' => '1'];
        $this->post('/admin/platform/vendors/2/subscription', $data)->assertSessionHasNoErrors();
        $receipt = BillingRecord::first();
        $this->assertSame('899.25', $receipt->amount);
        $this->assertSame(1, Subscription::where('is_active', true)->count());
        $this->assertSame(0, Subscription::where('is_active', true)->first()->invoice_used);
        $this->post('/admin/platform/billing/'.$receipt->id.'/reverse', ['payment_date' => today()->toDateString(), 'reference' => 'REFUND-BOOK-ENTRY', 'reason' => 'Actual receipt correction', 'request_key' => Str::uuid()->toString(), 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, BillingRecord::count());
        $this->assertSame('received', $receipt->fresh()->kind);
        $this->get('/admin/platform')->assertOk()->assertViewHas('summary', fn ($x) => (float) $x['revenue'] === 0.0);
    }

    public function test_disabled_vendor_cannot_login_impersonate_or_continue_session(): void
    {
        AccountControl::create(['user_id' => 2, 'disabled' => true, 'actor_id' => 1, 'reason' => 'Access disabled explicitly']);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => $this->f['vendor']->email, 'password' => 'Synthetic-Password-42'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($this->f['admin']);
        $this->post('/admin/vendors/2/login')->assertForbidden();
        $this->assertSame(1, Invoice::count());
    }

    public function test_support_threads_are_owned_append_only_and_admin_reply_notifies_owner(): void
    {
        $this->post('/support', ['subject' => 'Need assistance', 'body' => 'Please help with a real issue'])->assertSessionHasNoErrors();
        $ticket = SupportTicket::first();
        $this->actingAs($this->f['otherVendor']);
        $this->get('/support/'.$ticket->id)->assertForbidden();
        $this->post('/support/'.$ticket->id.'/reply', ['body' => 'Cannot access'])->assertForbidden();
        $this->actingAs($this->f['admin']);
        $this->post('/support/'.$ticket->id.'/reply', ['body' => 'Administrator response', 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame(2, $ticket->messages()->count());
        $this->assertSame(1, BusinessNotification::where('user_id', 2)->count());
        $this->actingAs($this->f['vendor']);
        $this->get('/support/'.$ticket->id)->assertOk()->assertSee('Administrator response');
    }

    public function test_email_requires_opt_in_transport_and_explicit_owner_action_then_queues_once(): void
    {
        Mail::fake();
        $notice = BusinessNotification::create(['user_id' => 2, 'event_key' => 'test-owned', 'title' => 'Account update', 'body' => 'Synthetic update', 'target' => '/notifications']);
        $this->post('/notifications/'.$notice->id.'/email', ['confirm' => 1])->assertSessionHasErrors('email');
        $this->assertSame(0, NotificationDelivery::count());
        app(Platform::class)->set('email.enabled', true);
        config(['mail.default' => 'smtp']);
        UserPreference::create(['user_id' => 2, 'email_enabled' => true]);
        $this->post('/notifications/'.$notice->id.'/email', ['confirm' => 1])->assertSessionHasNoErrors();
        $this->post('/notifications/'.$notice->id.'/email', ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, NotificationDelivery::count());
        Mail::assertNothingSent();
        $this->assertSame(1, app(EmailOutbox::class)->deliver());
        Mail::assertSent(BusinessUpdate::class, 1);
        $this->assertSame('submitted', NotificationDelivery::first()->status);
        $this->actingAs($this->f['otherVendor']);
        $this->post('/notifications/'.$notice->id.'/email', ['confirm' => 1])->assertForbidden();
    }

    public function test_plan_feature_disabled_blocks_new_records_but_keeps_history_reads(): void
    {
        PlanEntitlement::create(['subscription_plan_id' => $this->f['plan']->id, 'feature' => 'quotations', 'enabled' => false]);
        $this->get('/quotations')->assertOk();
        $this->post('/quotations', [])->assertForbidden();
        $this->f['subscription']->update(['end_date' => today()->subDay()->toDateString()]);
        $this->post('/quotations', [])->assertForbidden();
    }

    public function test_admin_and_vendor_platform_pages_render_and_notification_reads_are_owned(): void
    {
        foreach (['/notifications', '/support'] as $url) {
            $this->get($url)->assertOk();
        }$notice = BusinessNotification::create(['user_id' => 3, 'event_key' => 'other', 'title' => 'Private other update', 'body' => 'Private body', 'target' => '/notifications']);
        $this->post('/notifications/'.$notice->id.'/read')->assertForbidden();
        $this->actingAs($this->f['admin']);
        foreach (['/admin/platform', '/admin/platform/vendors/2', '/admin/platform/entitlements', '/admin/platform/integrations', '/admin/platform/audit', '/support'] as $url) {
            $r = $this->get($url)->assertOk();
            $this->assertDoesNotMatchRegularExpression('/<style\b|<script(?![^>]*\bsrc=)/i', $r->getContent());
        }
    }

    public function test_language_preferences_translate_ui_and_keep_business_data_unchanged(): void
    {
        $before = $this->f['invoice']->fresh()->getAttributes();
        foreach (['gu' => 'ભાવપત્રકો', 'hi' => 'कोटेशन', 'en' => 'Quotations'] as $locale => $label) {
            $this->post('/preferences/locale', ['locale' => $locale])->assertSessionHasNoErrors();
            $this->get('/quotations')->assertOk()->assertSee($label)->assertSee('lang="'.$locale.'"', false);
            $this->assertSame($before, $this->f['invoice']->fresh()->getAttributes());
        }
        $this->get('/dashboard')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_guest_language_is_session_scoped_and_validation_is_localized(): void
    {
        auth()->logout();
        $this->post('/locale', ['locale' => 'gu'])->assertSessionHas('bizpilot.locale', 'gu');
        $this->get('/login')->assertOk()->assertSee('lang="gu"', false);
        $this->post('/locale', ['locale' => 'unknown'])->assertSessionHasErrors('locale');
        app()->setLocale('hi');
        $this->assertIsString(__('Pagination'));
        $this->assertNotSame('The name field is required.', validator([], ['name' => 'required'])->errors()->first('name'));
    }

    public function test_indic_pdf_uses_unicode_renderer_and_keeps_money_visible(): void
    {
        $pdf = new UnicodePdf('<html><body><h1>ગુજરાતી ग्राहक</h1><p>INR 1,180.25</p></body></html>');
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertSame(1, $pdf->getPageCount());
        $this->assertSame('application/pdf', $pdf->download('invoice.pdf')->headers->get('Content-Type'));
    }

    public function test_translated_invoice_pdf_fits_a4_and_preserves_stored_totals(): void
    {
        $before = $this->f['invoice']->fresh()->getAttributes();
        app()->setLocale('gu');
        $pdf = app(InvoicePdf::class)->render($this->f['invoice']);
        $this->assertInstanceOf(UnicodePdf::class, $pdf);
        $this->assertSame(1, $pdf->getPageCount());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertSame($before, $this->f['invoice']->fresh()->getAttributes());
    }

    public function test_business_setup_is_read_only_scoped_and_reports_optional_services_honestly(): void
    {
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)/i', $q->sql)) {
                $writes[] = $q->sql;
            }
        });
        $this->get('/business-setup')->assertOk()->assertViewHas('setup', fn ($s) => $s['subscription_status'] === 'Ready' && $s['pending_reviews'] === 1 && ! $s['inventory_enabled'] && ! $s['email_ready']);
        $this->assertSame([], $writes);
        $this->actingAs($this->f['otherVendor']);
        $this->get('/business-setup')->assertOk()->assertViewHas('setup', fn ($s) => $s['subscription_status'] === 'No active subscription' && $s['pending_reviews'] === 0)->assertDontSee('Patel Trading Co.');
        $this->actingAs($this->f['customer']);
        $this->get('/business-setup')->assertForbidden();
        $this->actingAs($this->f['admin']);
        $this->get('/business-setup')->assertForbidden();
        $this->get('/admin/platform/vendors/2')->assertOk()->assertSee('Business readiness');
    }

    public function test_plan_access_edits_preserve_prices_quota_and_other_features_and_reject_stale_forms(): void
    {
        $this->actingAs($this->f['admin']);
        $p = app(Platform::class);
        $plan = $this->f['plan'];
        $before = $plan->fresh()->getAttributes();
        $sub = $this->f['subscription']->fresh()->getAttributes();
        $data = ['feature' => 'inventory', 'enabled' => '0', 'original_version' => $p->entitlementVersion($plan->id, 'inventory')];
        $this->post('/admin/platform/entitlements/'.$plan->id, $data)->assertSessionHasNoErrors();
        $this->assertFalse($p->allowance(2, 'inventory')['enabled']);
        $this->assertTrue($p->allowance(2, 'catalog')['enabled']);
        $this->post('/admin/platform/entitlements/'.$plan->id, array_replace($data, ['enabled' => '1']))->assertSessionHasErrors('original_version');
        $this->assertFalse($p->allowance(2, 'inventory')['enabled']);
        $this->assertSame($before, $plan->fresh()->getAttributes());
        $this->assertSame($sub, $this->f['subscription']->fresh()->getAttributes());
        $this->get('/admin/platform/entitlements')->assertOk()->assertSee('enabled-'.$plan->id.'-inventory', false);
        $this->actingAs($this->f['vendor']);
        $this->post('/admin/platform/entitlements/'.$plan->id, $data)->assertForbidden();
    }

    public function test_business_setup_detects_expired_exhausted_and_conflicting_subscriptions(): void
    {
        $setup = app(BusinessSetup::class);
        $v = $this->f['vendor'];
        $this->f['subscription']->update(['end_date' => today()->subDay()->toDateString()]);
        $this->assertSame('Expired', $setup->summary($v)['subscription_status']);
        $this->f['subscription']->update(['end_date' => today()->addDay()->toDateString(), 'invoice_used' => 100]);
        $this->assertSame('Invoice limit reached', $setup->summary($v)['subscription_status']);
        $this->f['subscription']->replicate()->save();
        $this->assertSame('Needs review', $setup->summary($v)['subscription_status']);
        $this->assertFalse($setup->summary($v)['features']['catalog']['enabled']);
    }

    public function test_removed_ai_routes_settings_and_draft_hooks_cannot_be_used(): void
    {
        $p = app(Platform::class);
        $p->set('ai.enabled', true);
        $p->set('ai.key', 'synthetic-historical-key-never-echo');
        PlanEntitlement::create(['subscription_plan_id' => $this->f['plan']->id, 'feature' => 'ai', 'enabled' => true, 'limit_value' => 100]);
        foreach (['/ai', '/ai/1'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/ai', ['question' => 'test'])->assertNotFound();
        $this->assertFalse($p->allowance(2, 'ai')['enabled']);
        $this->get('/business-setup')->assertOk()->assertDontSee('AI assistant');
        $this->get('/invoices/create?ai_request=1')->assertOk()->assertDontSee('synthetic-historical-key-never-echo');
        $this->get('/quotations/create?ai_request=1')->assertOk();
        $this->actingAs($this->f['admin']);
        foreach (['/admin/platform', '/admin/platform/entitlements', '/admin/platform/integrations', '/admin/platform/audit'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('OpenAI')->assertDontSee('Monthly AI request credits')->assertDontSee('synthetic-historical-key-never-echo');
        }
        $this->post('/admin/platform/integrations', ['enabled' => 1])->assertStatus(405);
        $this->post('/admin/platform/entitlements/'.$this->f['plan']->id, ['feature' => 'ai', 'enabled' => 1, 'original_version' => $p->entitlementVersion($this->f['plan']->id, 'ai')])->assertSessionHasErrors('feature');
        $this->assertSame(1, DB::table('plan_entitlements')->where('feature', 'ai')->count());
        Http::assertNothingSent();
    }

    private function planData(array $extra = []): array
    {
        return array_replace(['name' => 'New monthly plan', 'price' => '499.00', 'duration_days' => 30, 'invoice_limit' => 100, 'status' => 0, 'features' => array_fill_keys(array_keys(BusinessSetup::FEATURES), 1)], $extra);
    }

    public function test_plan_creation_saves_exact_price_modules_and_does_not_assign_or_bill_vendors(): void
    {
        $this->actingAs($this->f['admin']);
        $before = Subscription::count();
        $data = $this->planData();
        $data['features']['inventory'] = 0;
        $this->post('/subscription-plans', $data)->assertRedirect(route('subscription-plans.index'))->assertSessionHasNoErrors();
        $plan = SubscriptionPlan::where('name', $data['name'])->firstOrFail();
        $this->assertSame('499.00', Money::decimal(Money::cents($plan->price)));
        $this->assertSame(5, PlanEntitlement::where('subscription_plan_id', $plan->id)->count());
        $this->assertFalse(PlanEntitlement::where('subscription_plan_id', $plan->id)->where('feature', 'inventory')->first()->enabled);
        $this->assertSame($before, Subscription::count());
        $this->assertSame(0, BillingRecord::count());
        $this->post('/subscription-plans', $this->planData(['name' => '  New monthly plan  ']))->assertSessionHasErrors('name');
        $this->post('/subscription-plans', $this->planData(['name' => 'Bad precision', 'price' => '1.234']))->assertSessionHasErrors('price');
    }

    public function test_used_plan_terms_are_locked_while_name_availability_and_access_can_be_updated(): void
    {
        $this->actingAs($this->f['admin']);
        $plan = $this->f['plan']->fresh();
        $before = $plan->getAttributes();
        $sub = $this->f['subscription']->fresh()->getAttributes();
        $catalog = app(PlanCatalog::class);
        $d = $this->planData(['name' => $plan->name, 'price' => '249.00', 'duration_days' => 30, 'invoice_limit' => 100, 'status' => 1, 'original_version' => $catalog->version($plan)]);
        $this->put('/subscription-plans/'.$plan->id, array_replace($d, ['price' => '599.00']))->assertSessionHasErrors('plan');
        $this->assertSame($before, $plan->fresh()->getAttributes());
        $d['name'] = 'Starter revised label';
        $d['status'] = 0;
        $d['features']['expenses'] = 0;
        $this->put('/subscription-plans/'.$plan->id, $d)->assertSessionHasNoErrors();
        $this->assertSame('Starter revised label', $plan->fresh()->name);
        $this->assertFalse(app(Platform::class)->allowance(2, 'expenses')['enabled']);
        $this->assertSame($sub, $this->f['subscription']->fresh()->getAttributes());
        $this->put('/subscription-plans/'.$plan->id, $d)->assertSessionHasErrors('original_version');
    }

    public function test_plan_create_is_blank_and_archive_preserves_history_and_guards_stale_feature_changes(): void
    {
        $this->actingAs($this->f['admin']);
        $plan = $this->f['plan']->fresh();
        $catalog = app(PlanCatalog::class);
        $before = SubscriptionPlan::count();
        $this->get('/subscription-plans/create?copy_from='.$plan->id)->assertOk()->assertViewMissing('templatePlan')->assertDontSee('Create revised plan')->assertDontSee('(new)');
        $this->assertSame($before, SubscriptionPlan::count());
        $this->get('/subscription-plans')->assertOk()->assertDontSee('copy_from', false)->assertDontSee('Create revised plan');
        $this->get('/subscription-plans/'.$plan->id.'/edit')->assertOk()->assertDontSee('copy_from', false)->assertDontSee('Create revised plan');
        $token = $catalog->version($plan);
        PlanEntitlement::create(['subscription_plan_id' => $plan->id, 'feature' => 'inventory', 'enabled' => false]);
        $this->post('/subscription-plans/'.$plan->id.'/archive', ['original_version' => $token])->assertSessionHasErrors('original_version');
        $sub = $this->f['subscription']->fresh()->getAttributes();
        $withCount = SubscriptionPlan::withCount('subscriptions')->findOrFail($plan->id);
        $this->post('/subscription-plans/'.$plan->id.'/archive', ['original_version' => $catalog->version($withCount)])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $plan->fresh()->status);
        $this->assertSame($sub, $this->f['subscription']->fresh()->getAttributes());
        $this->actingAs($this->f['vendor']);
        $this->post('/subscription-plans/'.$plan->id.'/archive', ['original_version' => $catalog->version($plan->fresh())])->assertForbidden();
    }

    public function test_unused_plan_with_feature_settings_cannot_be_deleted_with_a_foreign_key_error(): void
    {
        $this->actingAs($this->f['admin']);
        $p = $this->f['secondPlan'];
        PlanEntitlement::create(['subscription_plan_id' => $p->id, 'feature' => 'catalog', 'enabled' => true]);
        $this->delete('/subscription-plans/'.$p->id)->assertSessionHas('error');
        $this->assertNotNull($p->fresh());
    }
}
