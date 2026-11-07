<?php

namespace App\Services\Invoicing;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SubscriptionAccess
{
    public function check(User $vendor, bool $creating, bool $lock = false): Subscription
    {
        $query = Subscription::where('user_id', $vendor->id)->where('is_active', 1)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $subscriptions = $query->limit(2)->get();
        if ($subscriptions->count() !== 1) {
            $this->deny($subscriptions->isEmpty() ? 'Choose an active subscription before saving invoices.' : 'Multiple active subscriptions need an administrator review before saving invoices.');
        }
        $subscription = $subscriptions->first();
        $planQuery = SubscriptionPlan::whereKey($subscription->subscription_plan_id);
        if ($lock) {
            $planQuery->lockForUpdate();
        }
        $plan = $planQuery->first();
        if (! $plan) {
            $this->deny('The subscription plan is unavailable. Contact your administrator.');
        }
        $today = CarbonImmutable::today();
        if ($today->gt($subscription->end_date)) {
            $this->deny('Your subscription has expired. Renew it before saving invoices.');
        }
        if ($today->lt($subscription->start_date)) {
            $this->deny('Your subscription has not started yet.');
        }
        if ($creating && $plan->invoice_limit !== null && $subscription->invoice_used >= $plan->invoice_limit) {
            $this->deny('Invoice limit reached. Choose a plan with more invoices.');
        }

        return $subscription;
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['subscription' => $message]);
    }
}
