<?php

namespace App\Http\Controllers;

use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Business\Guard;
use App\Services\Business\Platform;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user()->role === 'vendor', 403);

            return $next($request);
        });
    }

    public function index()
    {
        $plans = SubscriptionPlan::where('status', 1)->get();
        $currentSubscription = Subscription::with('plan')->where('user_id', auth()->id())->where('is_active', 1)->orderByDesc('id')->first();
        $planFeatures = app(Platform::class)->ready() ? PlanEntitlement::whereIn('subscription_plan_id', $plans->pluck('id'))->get()->groupBy('subscription_plan_id') : collect();

        return view('subscription.index', compact('plans', 'currentSubscription', 'planFeatures'));
    }

    public function subscribe($planId)
    {
        if (app(Platform::class)->ready()) {
            $ticket = DB::transaction(function () use ($planId) {
                Guard::vendor(auth()->user());
                $plan = SubscriptionPlan::whereKey($planId)->where('status', true)->firstOrFail();
                $ticket = SupportTicket::where('vendor_id', auth()->id())->where('subject', 'Subscription request #'.$plan->id)->whereIn('status', ['open', 'in_progress'])->first();
                if (! $ticket) {
                    $ticket = SupportTicket::create(['vendor_id' => auth()->id(), 'subject' => 'Subscription request #'.$plan->id]);
                    $ticket->messages()->create(['actor_id' => auth()->id(), 'body' => 'Please review my request for plan '.$plan->name.'. No online payment was collected and my quota has not been reset.']);
                }

                return $ticket;
            }, 5);

            return redirect()->route('support.show', $ticket)->with('success', 'Plan request sent to the administrator. Existing subscription and usage are unchanged.');
        }
        $activated = DB::transaction(function () use ($planId) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $active = Subscription::where('user_id', auth()->id())->where('is_active', 1)->lockForUpdate()->get();
            $plan = SubscriptionPlan::whereKey($planId)->where('status', 1)->lockForUpdate()->firstOrFail();
            if ($active->count() === 1 && (int) $active->first()->subscription_plan_id === (int) $plan->id && today()->lte($active->first()->end_date)) {
                return false;
            }
            Subscription::where('user_id', auth()->id())->where('is_active', 1)->update(['is_active' => 0]);
            Subscription::create(['user_id' => auth()->id(), 'subscription_plan_id' => $plan->id, 'start_date' => today(), 'end_date' => today()->addDays($plan->duration_days), 'invoice_used' => 0, 'is_active' => 1]);

            return true;
        }, 5);

        return redirect()->route('subscription')->with('success', $activated ? 'Subscription activated successfully' : 'This plan is already active. Your usage has not been reset.');
    }
}
