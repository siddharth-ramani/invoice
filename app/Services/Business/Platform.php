<?php

namespace App\Services\Business;

use App\Models\AuditEvent;
use App\Models\PlanEntitlement;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class Platform
{
    private ?bool $ready = null;

    public function ready(): bool
    {
        return (bool) config('bizpilot.platform_enabled', false) && ($this->ready ??= Schema::hasTable('platform_settings'));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        if (! $this->ready()) {
            return $default;
        }$value = PlatformSetting::where('key', $key)->value('value');
        if ($value === null) {
            return $default;
        }

        return json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value): void
    {
        PlatformSetting::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR))]);
    }

    public function audit(string $action, string $type, ?int $id, array $details = [], ?int $vendor = null): void
    {
        if ($this->ready() && auth()->check()) {
            AuditEvent::create(['actor_id' => auth()->id(), 'admin_actor_id' => Guard::admin(), 'vendor_id' => $vendor, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'details' => $details]);
        }
    }

    public function allowance(int $vendor, string $feature): array
    {
        if (! array_key_exists($feature, BusinessSetup::FEATURES)) {
            return ['enabled' => false, 'limit' => 0];
        }
        if (! $this->ready()) {
            return ['enabled' => true, 'limit' => 0];
        }
        $subs = Subscription::where('user_id', $vendor)->where('is_active', true)->limit(2)->get();
        if ($subs->count() > 1) {
            return ['enabled' => false, 'limit' => 0];
        }
        if ($subs->count() === 0) {
            return ['enabled' => true, 'limit' => 0];
        }
        $row = PlanEntitlement::where('subscription_plan_id', $subs->first()->subscription_plan_id)->where('feature', $feature)->first();

        return ['enabled' => $row ? (bool) $row->enabled : true, 'limit' => $row?->limit_value ?? 0];
    }

    public function entitlementVersion(int $plan, string $feature): string
    {
        $row = PlanEntitlement::where('subscription_plan_id', $plan)->where('feature', $feature)->first();

        return hash_hmac('sha256', json_encode([$plan, $feature, $row?->getAttributes()], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
