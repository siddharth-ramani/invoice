<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionPlanRequest;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Business\Modules;
use App\Services\Business\PlanCatalog;
use App\Services\Business\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index()
    {
        $plans = SubscriptionPlan::withCount('subscriptions')->latest()->get();

        return view('subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('subscription-plans.create');
    }

    public function store(SubscriptionPlanRequest $request)
    {
        app(PlanCatalog::class)->save($request->validated());

        return redirect()->route('subscription-plans.index')->with('success', 'Plan created successfully.');
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return redirect()->route('subscription-plans.edit', $subscriptionPlan);
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        $termsLocked = Subscription::where('subscription_plan_id', $subscriptionPlan->id)->exists();
        $planVersion = app(PlanCatalog::class)->version($subscriptionPlan);
        $featureAccess = app(Platform::class)->ready() ? PlanEntitlement::where('subscription_plan_id', $subscriptionPlan->id)->pluck('enabled', 'feature')->all() : [];

        return view('subscription-plans.edit', compact('subscriptionPlan', 'termsLocked', 'planVersion', 'featureAccess'));
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan)
    {
        app(PlanCatalog::class)->save($request->validated(), $subscriptionPlan);

        return redirect()->route('subscription-plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        if (Subscription::where('subscription_plan_id', $subscriptionPlan->id)->exists()) {
            return back()->with('error', 'This plan has subscriptions. Set it inactive instead of deleting it.');
        }
        $deleted = DB::transaction(function () use ($subscriptionPlan) {
            $saved = SubscriptionPlan::whereKey($subscriptionPlan->id)->lockForUpdate()->firstOrFail();
            if (Subscription::where('subscription_plan_id', $saved->id)->exists() || (app(Modules::class)->installed('plan_entitlements') && PlanEntitlement::where('subscription_plan_id', $saved->id)->exists())) {
                return false;
            }$saved->delete();

            return true;
        });
        if (! $deleted) {
            return back()->with('error', 'This plan has saved history or feature settings. Make it inactive instead of deleting it.');
        }

        return redirect()->route('subscription-plans.index')->with('success', 'Plan deleted.');
    }

    public function archive(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $data = $request->validate(['original_version' => 'required|string|size:64']);
        app(PlanCatalog::class)->archive($subscriptionPlan, $data['original_version']);

        return back()->with('success', 'Plan made inactive. Existing subscriptions and history are retained.');
    }
}
