@extends('layouts.admin')
@section('title',__('Plan features'))
@section('page-title',__('Platform management'))
@section('content')
<x-ui.page-header :title="__('Plan features')" :description="__('Review current access before changing one feature. Prices, subscriptions and used invoice quotas are unchanged.')" :eyebrow="__('Administration')"><x-ui.button :href="route('platform.index')" variant="secondary" icon="back">{{ __('Back') }}</x-ui.button></x-ui.page-header>
@foreach($plans as $plan)
<x-ui.card :title="$plan->name"><p>₹{{ number_format($plan->price,2) }} · {{ $plan->duration_days }} {{ __('days') }} · {{ __('Invoice limit') }}: {{ $plan->invoice_limit ?? __('Unlimited') }}</p>
@foreach(\App\Services\Business\BusinessSetup::FEATURES as $feature=>$label)
@php $saved=$entitlements->first(fn($r)=>(int)$r->subscription_plan_id===$plan->id&&$r->feature===$feature); $access=$saved ? (bool)$saved->enabled : true; @endphp
<div class="app-feature-row"><h3>{{ __($label) }}</h3><form method="POST" action="{{ route('platform.entitlements.update',$plan) }}" class="app-feature-form">@csrf<input type="hidden" name="feature" value="{{ $feature }}"><input type="hidden" name="original_version" value="{{ app(\App\Services\Business\Platform::class)->entitlementVersion($plan->id,$feature) }}"><x-ui.select name="enabled" :id="'enabled-'.$plan->id.'-'.$feature" :label="__('Access')"><option value="1" @selected($access)>{{ __('Enabled') }}</option><option value="0" @selected(!$access)>{{ __('Disabled for new actions') }}</option></x-ui.select>
<p class="form-text">{{ __('Existing saved history is retained. Inventory still requires each business to opt in.') }}</p>
<div class="app-actions"><x-ui.button variant="secondary">{{ __('Save feature') }}</x-ui.button></div></form></div>
@endforeach
</x-ui.card>
@endforeach
@endsection
