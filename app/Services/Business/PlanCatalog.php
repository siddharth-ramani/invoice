<?php

namespace App\Services\Business;

use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payments\Money;
use Illuminate\Support\Facades\DB;

class PlanCatalog
{
    public function version(SubscriptionPlan $plan): string
    {
        $features = app(Platform::class)->ready() ? PlanEntitlement::where('subscription_plan_id', $plan->id)->orderBy('feature')->get()->map(fn ($r) => $r->getAttributes())->all() : [];
        $attributes = array_intersect_key($plan->getAttributes(), array_flip(['id', 'name', 'price', 'duration_days', 'invoice_limit', 'status', 'created_at', 'updated_at']));
        ksort($attributes);

        return hash_hmac('sha256', json_encode([$attributes, $features], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function save(array $data, ?SubscriptionPlan $plan = null): SubscriptionPlan
    {
        return DB::transaction(function () use ($data, $plan) {
            $saved = $plan ? SubscriptionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail() : null;
            if ($saved && ! hash_equals($this->version($saved), $data['original_version'])) {
                Guard::fail('original_version', 'This plan changed in another tab. Reload before saving.');
            }
            $fields = ['name' => trim($data['name']), 'price' => Money::decimal(Money::cents($data['price'])), 'duration_days' => (int) $data['duration_days'], 'invoice_limit' => isset($data['invoice_limit']) ? (int) $data['invoice_limit'] : null, 'status' => (bool) $data['status']];
            if (SubscriptionPlan::whereRaw('LOWER(name) = ?', [mb_strtolower($fields['name'])])->when($saved, fn ($q) => $q->whereKeyNot($saved->id))->exists()) {
                Guard::fail('name', 'A plan with this name already exists.');
            }
            if ($saved && Subscription::where('subscription_plan_id', $saved->id)->exists() && ($fields['price'] !== Money::decimal(Money::cents($saved->price)) || $fields['duration_days'] !== (int) $saved->duration_days || $fields['invoice_limit'] !== ($saved->invoice_limit === null ? null : (int) $saved->invoice_limit))) {
                Guard::fail('plan', 'Pricing, duration and invoice limits are locked after subscription use. Create a new plan for new terms.');
            }
            $before = $saved?->getAttributes();
            if ($saved) {
                $saved->update($fields);
            } else {
                $saved = SubscriptionPlan::create($fields);
            }
            if (app(Platform::class)->ready() && isset($data['features'])) {
                foreach (BusinessSetup::FEATURES as $key => $label) {
                    PlanEntitlement::updateOrCreate(['subscription_plan_id' => $saved->id, 'feature' => $key], ['enabled' => (bool) $data['features'][$key], 'limit_value' => null]);
                }
            }
            app(Platform::class)->audit($plan ? 'plan.updated' : 'plan.created', 'plan', $saved->id, ['before' => $before, 'after' => $saved->fresh()->getAttributes(), 'features' => $data['features'] ?? null]);

            return $saved->fresh();
        }, 5);
    }

    public function archive(SubscriptionPlan $plan, string $version): void
    {
        DB::transaction(function () use ($plan, $version) {
            $saved = SubscriptionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->version($saved), $version)) {
                Guard::fail('original_version', 'This plan changed in another tab. Reload before saving.');
            }$saved->update(['status' => false]);
            app(Platform::class)->audit('plan.archived', 'plan', $saved->id, ['existing_subscriptions_retained' => true]);
        }, 5);
    }
}
