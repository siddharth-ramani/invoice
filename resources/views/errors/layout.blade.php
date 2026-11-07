<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · BizPilot AI</title>
<meta name="robots" content="noindex, nofollow">
@include('partials.favicon')
<link href="{{ asset('assets/css/style.css') }}" rel="stylesheet"></head><body class="app-auth"><main class="auth-main"><div class="auth-form-wrap"><p class="bp-eyebrow">BizPilot AI</p><h1>@yield('title')</h1><p>@yield('message')</p><div class="app-actions"><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">{{ __('Return to workspace') }}</a><a class="btn btn-secondary" href="{{ route('login') }}">{{ __('Sign in') }}</a></div></div></main></body></html>
