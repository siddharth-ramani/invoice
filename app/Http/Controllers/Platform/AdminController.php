<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AccountControl;
use App\Models\AuditEvent;
use App\Models\BillingRecord;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Business\BusinessSetup;
use App\Services\Business\Guard;
use App\Services\Business\ManualSubscriptions;
use App\Services\Business\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $vendors = User::where('role', 'vendor')->when($r->q, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$s.'%')->orWhere('company_name', 'like', '%'.$s.'%')))->orderByDesc('id')->paginate(20)->withQueryString();
        $revenue = BillingRecord::selectRaw("COALESCE(SUM(CASE WHEN kind='received' THEN amount ELSE -amount END),0) AS net")->first();
        $summary = ['vendors' => User::where('role', 'vendor')->count(), 'support' => SupportTicket::where('status', '<>', 'closed')->count(), 'revenue' => $revenue->net];

        return view('platform.index', compact('vendors', 'summary'));
    }

    public function vendor(User $vendor)
    {
        abort_unless($vendor->role === 'vendor', 404);
        $current = Subscription::with('plan')->where('user_id', $vendor->id)->where('is_active', true)->orderByDesc('id')->first();
        $plans = SubscriptionPlan::where('status', true)->orderBy('name')->get();
        $control = AccountControl::where('user_id', $vendor->id)->first();
        $billing = BillingRecord::where('vendor_id', $vendor->id)->orderByDesc('id')->paginate(10);
        $events = SubscriptionEvent::where('vendor_id', $vendor->id)->orderByDesc('id')->limit(10)->get();

        $setup = app(BusinessSetup::class)->summary($vendor);

        return view('platform.vendor', compact('vendor', 'current', 'plans', 'control', 'billing', 'events', 'setup'));
    }

    public function assign(Request $r, User $vendor)
    {
        $data = $r->validate(['subscription_plan_id' => 'required|integer|exists:subscription_plans,id', 'mode' => 'required|in:extend,replace', 'expected_active_id' => 'nullable|integer', 'expected_end_date' => 'nullable|date_format:Y-m-d', 'request_key' => 'required|uuid', 'reason' => 'required|string|min:5|max:1000', 'confirm' => 'accepted', 'billing_amount' => 'nullable|numeric|regex:/^\d{1,10}(\.\d{1,2})?$/D|min:0|max:9999999999.99', 'payment_date' => 'nullable|date_format:Y-m-d|before_or_equal:'.now(config('bizpilot.timezone'))->toDateString(), 'reference' => 'nullable|string|max:100', 'confirm_payment' => 'sometimes|accepted']);
        app(ManualSubscriptions::class)->assign($r->user(), $vendor, $data);

        return back()->with('success', 'Subscription updated manually. Revenue recorded only if an actual payment was confirmed.');
    }

    public function control(Request $r, User $vendor)
    {
        abort_unless($vendor->role === 'vendor', 403);
        $data = $r->validate(['disabled' => 'required|boolean', 'reason' => 'required|string|min:5|max:1000', 'confirm' => 'accepted']);
        DB::transaction(function () use ($r, $vendor, $data) {
            Guard::vendor($vendor);
            AccountControl::updateOrCreate(['user_id' => $vendor->id], ['disabled' => $data['disabled'], 'reason' => $data['reason'], 'actor_id' => $r->user()->id]);
            app(Platform::class)->audit('account.'.($data['disabled'] ? 'disabled' : 'enabled'), 'user', $vendor->id, ['reason' => $data['reason']], $vendor->id);
        }, 5);

        return back()->with('success', 'Account access updated. Business records retained.');
    }

    public function entitlements(Request $r, SubscriptionPlan $plan)
    {
        $data = $r->validate(['feature' => 'required|in:quotations,catalog,expenses,inventory,analytics', 'enabled' => 'required|boolean', 'limit_value' => 'nullable|integer|min:0|max:1000000', 'original_version' => 'required|string|size:64']);
        DB::transaction(function () use ($plan, $data) {
            SubscriptionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals(app(Platform::class)->entitlementVersion($plan->id, $data['feature']), $data['original_version'])) {
                Guard::fail('original_version', 'Plan access changed in another tab. Reload before saving.');
            }
            PlanEntitlement::updateOrCreate(['subscription_plan_id' => $plan->id, 'feature' => $data['feature']], ['enabled' => $data['enabled'], 'limit_value' => $data['limit_value'] ?? null]);
            app(Platform::class)->audit('plan.entitlement', 'plan', $plan->id, $data);
        }, 5);

        return back()->with('success', 'Plan feature access saved.');
    }

    public function plans()
    {
        $plans = SubscriptionPlan::orderBy('name')->get();
        $entitlements = PlanEntitlement::orderBy('subscription_plan_id')->get();

        return view('platform.entitlements', compact('plans', 'entitlements'));
    }

    public function reverse(Request $r, BillingRecord $billing)
    {
        $data = $r->validate(['payment_date' => 'required|date_format:Y-m-d|before_or_equal:'.now(config('bizpilot.timezone'))->toDateString(), 'reference' => 'required|string|max:100', 'reason' => 'required|string|min:5|max:1000', 'request_key' => 'required|uuid', 'confirm' => 'accepted']);
        app(ManualSubscriptions::class)->reverse($r->user(), $billing, $data);

        return back()->with('success', 'Billing reversal recorded; original record retained. This does not send a bank refund.');
    }

    public function audit()
    {
        $events = AuditEvent::orderByDesc('id')->paginate(30);

        return view('platform.audit', compact('events'));
    }
}
