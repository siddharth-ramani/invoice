@extends('layouts.app')
@section('title',__('Sign in'))
@section('content')
<div class="auth-layout auth-layout-rich">@include('auth.story')<main class="auth-main auth-main-rich">@include('auth.navigation',['authRoute'=>'login'])<div class="auth-form-wrap"><p class="bp-eyebrow">{{ __('Welcome back') }}</p><h2>{{ __('authflow.login_title') }}</h2><p class="auth-intro">{{ __('authflow.login_intro') }}</p><x-ui.alerts />
<form method="POST" action="{{ route('login') }}" class="auth-form">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() }}">
<x-ui.field name="email" :label="__('authflow.email')" type="email" :placeholder="__('authflow.email_placeholder')" required maxlength="255" autocomplete="username" autofocus />
<div class="auth-password"><x-ui.field name="password" :label="__('authflow.password')" type="password" required autocomplete="current-password" /><button type="button" class="auth-password-toggle" data-password-toggle data-password-target="password" aria-controls="password" aria-label="{{ __('authflow.show') }}" aria-pressed="false"><x-ui.icon name="eye" /></button></div>
<x-ui.button icon="arrow">{{ __('Sign in') }}</x-ui.button></form>
<p class="auth-account-switch">{{ __('authflow.no_account') }} <a href="{{ route('register',['lang'=>app()->getLocale()]) }}">{{ __('authflow.create_account') }} <span aria-hidden="true">↗</span></a></p>
<section class="auth-info-box" aria-labelledby="login-help-title"><span><x-ui.icon name="support" /></span><div><h3 id="login-help-title">{{ __('authflow.help_title') }}</h3><p>{{ __('authflow.help_body') }}</p></div></section>
<p class="auth-support"><x-ui.icon name="shield" /><span>{{ __('authflow.secure_note') }}</span></p></div><p class="auth-mobile-copyright">© {{ now()->year }} BizPilot AI</p></main></div>
@endsection
