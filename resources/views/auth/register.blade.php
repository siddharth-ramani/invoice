@extends('layouts.app')
@section('title',__('authflow.create_account'))
@section('content')
<div class="auth-layout auth-layout-rich">@include('auth.story')<main class="auth-main auth-main-rich">@include('auth.navigation',['authRoute'=>'register'])<div class="auth-form-wrap auth-register-wrap"><p class="bp-eyebrow">BizPilot</p><h2>{{ __('authflow.register_title') }}</h2><p class="auth-intro">{{ __('authflow.register_intro') }}</p><x-ui.alerts />
<form method="POST" action="{{ route('register') }}" class="auth-form">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() }}">
<div class="auth-fields-grid"><x-ui.field name="name" :label="__('authflow.name')" :placeholder="__('authflow.name_placeholder')" required maxlength="255" autocomplete="name" autofocus /><x-ui.field name="company_name" :label="__('authflow.business')" :placeholder="__('authflow.business_placeholder')" required maxlength="255" autocomplete="organization" /></div>
<x-ui.field name="email" :label="__('authflow.email')" type="email" :placeholder="__('authflow.email_placeholder')" required maxlength="255" autocomplete="email" />
<x-ui.field name="mobile" :label="__('authflow.mobile')" type="tel" :placeholder="__('authflow.mobile_placeholder')" maxlength="15" autocomplete="tel" />
<div class="auth-password"><x-ui.field name="password" :label="__('authflow.password')" type="password" required minlength="8" autocomplete="new-password" :help="__('authflow.password_help')" /><button type="button" class="auth-password-toggle" data-password-toggle data-password-target="password" aria-controls="password" aria-label="{{ __('authflow.show') }}" aria-pressed="false"><x-ui.icon name="eye" /></button></div>
<div class="auth-password"><x-ui.field name="password_confirmation" :label="__('authflow.confirm')" type="password" required minlength="8" autocomplete="new-password" /><button type="button" class="auth-password-toggle" data-password-toggle data-password-target="password-confirmation" aria-controls="password-confirmation" aria-label="{{ __('authflow.show') }}" aria-pressed="false"><x-ui.icon name="eye" /></button></div>
<p class="auth-form-note">{{ __('authflow.manual_note') }}</p><x-ui.button icon="arrow">{{ __('authflow.create_account') }}</x-ui.button></form>
<p class="auth-account-switch">{{ __('authflow.have_account') }} <a href="{{ route('login',['lang'=>app()->getLocale()]) }}">{{ __('Sign in') }}</a></p>
<section class="auth-setup-box" aria-labelledby="register-steps-title"><h3 id="register-steps-title">{{ __('authflow.setup_title') }}</h3><ol>@foreach(trans('authflow.setup_steps') as $step)<li><span>{{ $step }}</span></li>@endforeach</ol></section></div><p class="auth-mobile-copyright">© {{ now()->year }} BizPilot AI</p></main></div>
@endsection
