<?php

namespace App\Services\Business;

use App\Models\AccountControl;
use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\InvoiceReceivable;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\Carbon;

class BusinessSetup
{
    public const FEATURES = ['quotations' => 'Quotations', 'catalog' => 'Products & services', 'expenses' => 'Expenses', 'inventory' => 'Inventory', 'analytics' => 'Business analytics'];

    public function summary(User $vendor): array
    {
        abort_unless($vendor->role === 'vendor', 404);
        $subscriptions = Subscription::with('plan')->where('user_id', $vendor->id)->where('is_active', true)->orderBy('id')->limit(2)->get();
        $current = $subscriptions->count() === 1 ? $subscriptions->first() : null;
        $today = now(config('bizpilot.timezone'))->toDateString();
        $subscriptionStatus = $subscriptions->count() > 1 ? 'Needs review' : (! $current ? 'No active subscription' : (! $current->plan ? 'Plan unavailable' : (Carbon::parse($current->start_date)->toDateString() > $today ? 'Not started' : (Carbon::parse($current->end_date)->toDateString() < $today ? 'Expired' : ($current->plan->invoice_limit !== null && $current->invoice_used >= $current->plan->invoice_limit ? 'Invoice limit reached' : 'Ready')))));
        $platform = app(Platform::class);
        $features = [];
        foreach (self::FEATURES as $key => $label) {
            $features[$key] = $platform->allowance($vendor->id, $key);
        }
        $pendingReviews = Invoice::where('vendor_id', $vendor->id)->whereNotIn('id', InvoiceReceivable::where('vendor_id', $vendor->id)->whereNotNull('confirmed_at')->select('invoice_id'))->count();

        return ['subscription' => $current, 'subscription_status' => $subscriptionStatus, 'account_enabled' => ! AccountControl::where('user_id', $vendor->id)->where('disabled', true)->exists(), 'profile_ready' => filled($vendor->name) && filled($vendor->address), 'features' => $features, 'inventory_enabled' => InventorySetting::where('vendor_id', $vendor->id)->where('enabled', true)->exists(), 'pending_reviews' => $pendingReviews, 'email_ready' => app(EmailOutbox::class)->ready(), 'email_opted_in' => UserPreference::where('user_id', $vendor->id)->where('email_enabled', true)->exists()];
    }
}
