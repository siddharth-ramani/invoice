@extends('layouts.admin')
@section('title',__('Email notifications'))
@section('page-title',__('Platform management'))
@section('content')
<x-ui.page-header :title="__('Email notifications')" :description="__('Subscription payments remain manually managed.')" :eyebrow="__('Administration')"><x-ui.button :href="route('platform.index')" variant="secondary" icon="back">{{ __('Back') }}</x-ui.button></x-ui.page-header>
<x-ui.card :title="__('Email notifications')" :description="__('SMTP/provider credentials are managed in the existing environment configuration. Saving this setting sends nothing.')"><form method="POST" action="{{ route('platform.email.update') }}" class="app-form-grid">@csrf<x-ui.select name="enabled" id="email-delivery-enabled" :label="__('Email delivery')"><option value="0">{{ __('Disabled') }}</option><option value="1" @selected(app(\App\Services\Business\Platform::class)->setting('email.enabled',false))>{{ __('Allow opted-in explicit email requests') }}</option></x-ui.select><label class="payment-acknowledgement"><input type="checkbox" name="confirm" value="1" required><span>{{ __('I reviewed mail configuration. Only explicitly requested updates are queued; no customer reminders are automatically sent.') }}</span></label><div class="app-actions"><x-ui.button variant="secondary">{{ __('Save email setting') }}</x-ui.button></div></form></x-ui.card>
@endsection
