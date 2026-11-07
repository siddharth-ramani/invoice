<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', __('Welcome')) · BizPilot AI</title>
<meta name="robots" content="noindex, nofollow">
@include('partials.favicon')
<link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet"><link href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}" rel="stylesheet"></head>
<body class="app-auth">@yield('content')<script src="{{ asset('assets/js/i18n-'.(in_array(app()->getLocale(),['en','gu','hi'],true)?app()->getLocale():'en').'.js') }}" defer></script><script src="{{ asset('assets/js/auth.js') }}?v={{ filemtime(public_path('assets/js/auth.js')) }}" defer></script></body>
</html>
