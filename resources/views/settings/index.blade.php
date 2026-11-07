@extends('layouts.admin')
@section('title', __('Settings'))
@section('page-title', __('Settings'))
@section('content')
<x-ui.page-header :title="__('Business settings')" :description="__('Manage your profile, invoice details and account security.')" :eyebrow="__('Manage')" />
<form method="POST" action="{{ route('settings.update') }}" class="app-form-width">@csrf
<x-ui.card :title="__('Profile & contact')" :description="__('Your contact details and business identity.')"><div class="app-form-grid">
<x-ui.field name="name" :label="__('Your name')" :value="$user->name" required autocomplete="name" maxlength="255" />
<x-ui.field name="company_name" :label="__('Business name')" :value="$user->company_name" :placeholder="__('Your company or trading name')" autocomplete="organization" maxlength="255" />
<x-ui.field name="email" type="email" :label="__('Email address')" :value="$user->email" required autocomplete="email" maxlength="255" />
<x-ui.field name="mobile" type="tel" :label="__('Mobile number')" :value="$user->mobile" autocomplete="tel" maxlength="15" />
<x-ui.field name="mobile_2" type="tel" :label="__('Alternate contact number')" :value="$user->mobile_2" maxlength="15" />
<x-ui.field name="gst_number" :label="__('GSTIN')" :value="$user->gst_number" :placeholder="__('Business GST number')" maxlength="20" />
<div class="app-span-full"><x-ui.field name="address" type="textarea" :label="__('Business address')" :value="$user->address" rows="3" autocomplete="street-address" /></div>
</div></x-ui.card>
<x-ui.card :title="__('Invoice preferences')" :description="__('These details appear on your invoice documents.')"><div class="app-form-grid"><x-ui.field name="invoice_prefix" :label="__('Invoice prefix')" :value="$user->invoice_prefix" maxlength="3" :placeholder="__('GT')" :help="__('Up to 3 characters, for example GT/001.')" /><div class="app-span-full"><x-ui.field name="terms_conditions" type="textarea" :label="__('Terms & conditions')" :value="$user->terms_conditions" rows="4" :help="__('Write each condition on a separate line.')" /></div></div></x-ui.card>
<x-ui.card :title="__('Change password')" :description="__('authflow.current_help')"><div class="app-form-grid"><div class="app-span-full"><x-ui.field name="current_password" type="password" :label="__('authflow.current_password')" autocomplete="current-password" /></div><x-ui.field name="password" type="password" :label="__('New password')" minlength="8" autocomplete="new-password" /><x-ui.field name="password_confirmation" type="password" :label="__('Confirm new password')" minlength="8" autocomplete="new-password" /></div></x-ui.card>
<div class="app-form-footer"><span class="app-form-note">{{ __('Changes apply after you save.') }}</span><x-ui.button icon="check">{{ __('Save settings') }}</x-ui.button></div>
</form>
@endsection
