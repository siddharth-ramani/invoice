@extends('layouts.admin')
@section('title', __('Subscription'))
@section('page-title', __('Subscription'))
@section('content')
<x-ui.page-header :title="__('Your subscription')" :description="__('Review your current plan and available invoice limits.')" :eyebrow="__('Manage')"><x-ui.button :href="route('vendor.subscription')" variant="secondary" icon="card">{{ __('View usage') }}</x-ui.button></x-ui.page-header>
@if($currentSubscription && $currentSubscription->plan)
<div class="app-summary-grid"><div class="app-summary-card"><span>{{ __('Current plan') }}</span><strong>{{ $currentSubscription->plan->name }}</strong></div><div class="app-summary-card"><span>{{ __('Valid until') }}</span><strong>{{ \Carbon\Carbon::parse($currentSubscription->end_date)->format('d M Y') }}</strong></div><div class="app-summary-card"><span>{{ __('Invoices used') }}</span><strong>{{ $currentSubscription->invoice_used }} <small class="app-muted">/ {{ $currentSubscription->plan->invoice_limit ?? __('Unlimited') }}</small></strong></div></div>
@endif
@if($plans->isEmpty())
<x-ui.card><x-ui.empty-state :title="__('No plans available')" :description="__('No subscription plans are currently available for selection.')" icon="card" /></x-ui.card>
@else
<div class="app-plan-grid">
@foreach($plans as $plan)
@php $current = $currentSubscription && $currentSubscription->subscription_plan_id == $plan->id && !\Carbon\Carbon::today()->gt($currentSubscription->end_date); @endphp
<article class="app-plan-card {{ $current ? 'is-current' : '' }}"><div><p class="bp-eyebrow">{{ $current ? __('Your current plan') : __('Available plan') }}</p><h2>{{ $plan->name }}</h2></div><p class="app-plan-price">₹{{ number_format($plan->price, 2) }}<small>{{ $plan->duration_days }} {{ __('days') }}</small></p><ul class="app-plan-features"><li><x-ui.icon name="check" />{{ $plan->invoice_limit === null ? __('Unlimited invoices') : $plan->invoice_limit.' invoices' }}</li>@foreach(\App\Services\Business\BusinessSetup::FEATURES as $feature=>$label)@php $access=($planFeatures->get($plan->id)??collect())->firstWhere('feature',$feature); @endphp @if(!$access||$access->enabled)<li><x-ui.icon name="check" />{{ __($label) }}</li>@endif @endforeach</ul>
@if($current)<span class="bp-badge bp-badge-paid">{{ __('Current plan') }}</span>@else<form method="POST" action="{{ route('subscription.subscribe', $plan->id) }}" data-confirm-kind="plan" data-confirm="{{ app(\App\Services\Business\Platform::class)->ready() ? __('Request this plan from the administrator? Your existing quota stays unchanged.') : __('Activate this plan? No online payment is collected.') }}">@csrf<x-ui.button icon="card">{{ app(\App\Services\Business\Platform::class)->ready() ? __('Request plan') : ($currentSubscription ? __('Choose plan') : __('Activate plan')) }}</x-ui.button></form>@endif
</article>
@endforeach
</div>
<p class="app-muted mt-4">{{ app(\App\Services\Business\Platform::class)->ready() ? __('Plans are managed manually by the administrator. Requesting a plan does not activate it, reset usage or collect a payment.') : __('Plan activation uses the existing subscription system. No online payment is collected on this page.') }}</p>
@endif
@endsection
