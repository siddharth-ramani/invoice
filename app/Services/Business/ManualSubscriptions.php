<?php

namespace App\Services\Business;

use App\Models\BillingRecord;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ManualSubscriptions
{
    public function assign(User $admin, User $vendor, array $data): Subscription
    {
        abort_unless($admin->role === 'admin' && $vendor->role === 'vendor', 403);

        return DB::transaction(function () use ($admin, $vendor, $data) {
            Guard::vendor($vendor);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $event = SubscriptionEvent::where('vendor_id', $vendor->id)->where('request_key', $data['request_key'])->first();
            if ($event) {
                if (! hash_equals($event->request_hash, $hash)) {
                    Guard::fail('request_key', 'This submission key belongs to another subscription change.');
                }

                return Subscription::findOrFail($event->snapshot['after']['id']);
            }
            $active = Subscription::where('user_id', $vendor->id)->where('is_active', true)->lockForUpdate()->get();
            if ($active->count() > 1) {
                Guard::fail('subscription', 'Resolve multiple active subscriptions before assigning a plan.');
            }$current = $active->first();
            $currentEnd = $current ? Carbon::parse($current->end_date)->toDateString() : '';
            if ((int) ($data['expected_active_id'] ?? 0) !== (int) ($current?->id ?? 0) || ($data['expected_end_date'] ?? '') !== $currentEnd) {
                Guard::fail('subscription', 'Current subscription changed. Reload before assigning.');
            }
            $plan = SubscriptionPlan::whereKey($data['subscription_plan_id'])->where('status', true)->lockForUpdate()->firstOrFail();
            $before = $current?->getAttributes();
            $today = now(config('bizpilot.timezone'))->toDateString();
            if ($data['mode'] === 'extend') {
                if (! $current || (int) $current->subscription_plan_id !== $plan->id) {
                    Guard::fail('mode', 'Extend applies to the same current plan. Use a new period for a different plan.');
                }$base = max($today, $current->end_date);
                $current->update(['end_date' => Carbon::parse($base)->addDays($plan->duration_days)->toDateString()]);
                $saved = $current;
            } else {
                if ($current) {
                    $current->update(['is_active' => false]);
                }$saved = Subscription::create(['user_id' => $vendor->id, 'subscription_plan_id' => $plan->id, 'start_date' => $today, 'end_date' => Carbon::parse($today)->addDays($plan->duration_days - 1)->toDateString(), 'invoice_used' => 0, 'is_active' => true]);
            }
            SubscriptionEvent::create(['vendor_id' => $vendor->id, 'actor_id' => $admin->id, 'action' => $data['mode'], 'reason' => $data['reason'], 'request_key' => $data['request_key'], 'request_hash' => $hash, 'snapshot' => ['before' => $before, 'after' => $saved->fresh()->getAttributes()]]);
            $amount = Money::cents($data['billing_amount'] ?? 0);
            if ($amount > 0) {
                if (empty($data['confirm_payment']) || empty($data['payment_date']) || empty($data['reference'])) {
                    Guard::fail('billing_amount', 'Confirm an actual received payment with date and reference before recording revenue.');
                }BillingRecord::create(['vendor_id' => $vendor->id, 'actor_id' => $admin->id, 'subscription_id' => $saved->id, 'plan_name' => $plan->name, 'amount' => Money::decimal($amount), 'payment_date' => $data['payment_date'], 'reference' => $data['reference'], 'reason' => $data['reason'], 'request_key' => $data['request_key'], 'request_hash' => $hash, 'kind' => 'received']);
            }
            app(Platform::class)->audit('subscription.'.$data['mode'], 'subscription', $saved->id, ['plan_id' => $plan->id, 'billing_recorded' => $amount > 0], $vendor->id);
            app(Notifications::class)->add($vendor->id, 'subscription-event:'.$data['request_key'], 'Subscription updated', 'Your plan was updated manually by the administrator. Review your current invoice allowance.', route('vendor.subscription', [], false));

            return $saved;
        }, 5);
    }

    public function reverse(User $admin, BillingRecord $record, array $data): BillingRecord
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($admin, $record, $data) {
            $vendor = User::findOrFail($record->vendor_id);
            Guard::vendor($vendor);
            $saved = BillingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($saved->kind !== 'received' || BillingRecord::where('reverses_id', $saved->id)->exists()) {
                Guard::fail('billing', 'This billing receipt cannot be reversed again.');
            }if ($data['payment_date'] < $saved->payment_date) {
                Guard::fail('payment_date', 'Reversal date cannot precede the received payment.');
            }$reversed = BillingRecord::create(['vendor_id' => $vendor->id, 'actor_id' => $admin->id, 'subscription_id' => $saved->subscription_id, 'plan_name' => $saved->plan_name, 'amount' => $saved->amount, 'payment_date' => $data['payment_date'], 'reference' => $data['reference'], 'reason' => $data['reason'], 'request_key' => $data['request_key'], 'request_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'kind' => 'reversal', 'reverses_id' => $saved->id]);
            app(Platform::class)->audit('billing.reversed', 'billing_record', $saved->id, [], $vendor->id);

            return $reversed;
        }, 5);
    }
}
