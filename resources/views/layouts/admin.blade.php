<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Workspace')) · BizPilot AI</title>
<meta name="robots" content="noindex, nofollow">
@include('partials.favicon')

    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/datatables/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/datatables/responsive.bootstrap5.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}" rel="stylesheet">
    @stack('styles')
    <script src="{{ asset('assets/js/sidebar-state.js') }}?v={{ filemtime(public_path('assets/js/sidebar-state.js')) }}"></script>
</head>
<body class="app-shell bp-dashboard-shell">
<a class="app-skip-link" href="#main-content">{{ __('Skip to content') }}</a>
<button id="overlay" class="app-sidebar-overlay" type="button" aria-label="{{ __('Close navigation') }}" tabindex="-1" hidden></button>
<aside id="sidebar" class="app-sidebar" aria-label="{{ __('Main navigation') }}">
    <a class="app-brand" href="{{ auth()->user()->role === 'customer' ? route('settings') : route('dashboard') }}" aria-label="{{ __('BizPilot AI home') }}"><span class="app-brand-mark" aria-hidden="true">{{ __('B') }}<span>↗</span></span><span class="app-brand-name">BizPilot <strong>{{ __('AI') }}</strong></span></a>
    <button id="sidebarClose" class="app-icon-button app-sidebar-close" type="button" aria-label="{{ __('Close navigation') }}"><x-ui.icon name="close" /></button>
    <div class="app-sidebar-scroll">
        @if(in_array(auth()->user()->role, ['admin', 'vendor'], true))
        <p class="app-nav-group">{{ __('Workspace') }}</p>
        <nav aria-label="{{ __('Workspace') }}">
            <x-ui.nav-item route="dashboard" :label="__('Overview')" icon="home" :active="request()->routeIs('dashboard')" />
            <x-ui.nav-item route="customers.index" :label="auth()->user()->role === 'admin' ? __('Vendors') : __('Customers')" icon="people" :active="request()->routeIs('customers.*')" />
            @if(auth()->user()->role === 'vendor')
            <x-ui.nav-item route="invoices.index" :label="__('Invoices')" icon="document" :active="request()->routeIs('invoices.*')" />
            @if(app(\App\Services\Business\Modules::class)->ready('quotations'))
            <x-ui.nav-item route="quotations.index" :label="__('Quotations')" icon="document" :active="request()->routeIs('quotations.*')" />
            <x-ui.nav-item route="products.index" :label="__('Products & services')" icon="building" :active="request()->routeIs('products.*')" />
            <x-ui.nav-item route="inventory.index" :label="__('Inventory')" icon="building" :active="request()->routeIs('inventory.*')" />
            <x-ui.nav-item route="expenses.index" :label="__('Expenses')" icon="card" :active="request()->routeIs('expenses.*')" />
            @endif
            @if(app(\App\Services\Payments\PaymentModule::class)->ready())
            <x-ui.nav-item route="payments.index" :label="__('Payments')" icon="rupee" :active="request()->routeIs('payments.*')" />
            <x-ui.nav-item route="receivables.index" :label="__('Receivables')" icon="card" :active="request()->routeIs('receivables.*')" />
            @endif
            <x-ui.nav-item route="gst.index" :label="__('GST rates')" icon="tax" :active="request()->routeIs('gst.*')" />
            @endif
        </nav>
        @if(auth()->user()->role === 'vendor')
        <p class="app-nav-group">{{ __('Reports') }}</p>
        <nav aria-label="{{ __('Reports') }}">
            <x-ui.nav-item route="reports.sales" :label="__('Sales report')" icon="chart" :active="request()->routeIs('reports.sales')" />
            <x-ui.nav-item route="reports.gst" :label="__('GST report')" icon="tax" :active="request()->routeIs('reports.gst')" />
            @if(app(\App\Services\Business\Modules::class)->ready('expenses'))<x-ui.nav-item route="analytics.index" :label="__('Business analytics')" icon="chart" :active="request()->routeIs('analytics.*')" />@endif
        </nav>
        @endif
        @endif
        <p class="app-nav-group">{{ __('Manage') }}</p>
        <nav aria-label="{{ __('Account') }}">
            @if(auth()->user()->role === 'admin')<x-ui.nav-item route="subscription-plans.index" :label="__('Subscription plans')" icon="card" :active="request()->routeIs('subscription-plans.*')" />@endif
            @if(auth()->user()->role === 'vendor')<x-ui.nav-item route="subscription" :label="__('Subscription')" icon="card" :active="request()->routeIs('subscription', 'vendor.subscription')" />@endif
            @if(app(\App\Services\Business\Platform::class)->ready())
            @if(auth()->user()->role === 'admin')<x-ui.nav-item route="platform.index" :label="__('Platform management')" icon="shield" :active="request()->routeIs('platform.*')" />@endif
            @if(in_array(auth()->user()->role,['admin','vendor'],true))<x-ui.nav-item route="support.index" :label="__('Support')" icon="support" :active="request()->routeIs('support.*')" />@endif
            @if(auth()->user()->role === 'vendor')<x-ui.nav-item route="business.setup" :label="__('Business setup')" icon="check" :active="request()->routeIs('business.setup')" />@endif
            <x-ui.nav-item route="notifications.index" :label="__('Notifications & language')" icon="bell" :active="request()->routeIs('notifications.*')" />
            @endif
            <x-ui.nav-item route="settings" :label="__('Settings')" icon="settings" :active="request()->routeIs('settings')" />
        </nav>
    </div>
    <div class="app-sidebar-footer"><div class="app-workspace"><span class="app-workspace-icon"><x-ui.icon :name="auth()->user()->role === 'admin' ? 'shield' : 'building'" /></span><div><strong>{{ auth()->user()->role === 'admin' ? __('Administration') : __('Business workspace') }}</strong><span>BizPilot AI</span></div></div><form method="POST" action="{{ route('logout') }}"><x-ui.button variant="sidebar" icon="logout" :aria-label="__('Sign out')">{{ __('Sign out') }}</x-ui.button>@csrf</form></div>
</aside>
<div class="app-main">
    @if(session()->has('bizpilot.impersonation'))
    <section class="app-vendor-login-banner" aria-label="{{ __('Admin vendor access') }}"><div><x-ui.icon name="shield" /><span>{{ __('You are viewing') }} <strong>{{ auth()->user()->company_name ?: auth()->user()->name }}</strong> {{ __('as a vendor. Actions use this vendor’s account.') }}</span></div><form method="POST" action="{{ route('admin.vendors.return') }}">@csrf<x-ui.button variant="secondary" icon="back">{{ __('Return to admin') }}</x-ui.button></form></section>
    @endif
    <header class="app-topbar">
        <div class="app-topbar-start"><button id="toggleBtn" class="app-icon-button" type="button" aria-label="{{ __('Toggle navigation') }}" aria-controls="sidebar" aria-expanded="true"><x-ui.icon name="menu" /></button><nav class="app-breadcrumb" aria-label="{{ __('Breadcrumb') }}"><span class="app-breadcrumb-root">{{ __('Workspace') }}</span><x-ui.icon name="chevron" /><span aria-current="page">@yield('page-title', __('Overview'))</span></nav></div>
        <details class="app-profile"><summary aria-label="Open account menu for {{ auth()->user()->name }}"><span class="app-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="app-profile-name">{{ auth()->user()->name }}<small>{{ __(ucfirst(auth()->user()->role)) }}</small></span><x-ui.icon name="down" /></summary><div class="app-profile-menu">@if(session()->has('bizpilot.impersonation'))<form method="POST" action="{{ route('admin.vendors.return') }}">@csrf<button type="submit"><x-ui.icon name="back" />{{ __('Return to admin') }}</button></form>@endif<a href="{{ route('settings') }}"><x-ui.icon name="settings" />{{ __('Account settings') }}</a>@if(auth()->user()->role === 'vendor')<a href="{{ route('subscription') }}"><x-ui.icon name="card" />{{ __('My subscription') }}</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-ui.icon name="logout" />{{ __('Sign out') }}</button></form></div></details>
    </header>
    <main id="main-content" class="app-content" tabindex="-1"><x-ui.alerts />@yield('content')</main>
    <footer class="app-footer"><span>© {{ now()->year }} BizPilot AI</span><a href="{{ route('settings') }}">{{ __('Business settings') }}</a></footer>
</div>
<dialog id="confirmDialog" class="app-confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-description"><div class="app-confirm-icon"><span data-confirm-delete-icon><x-ui.icon name="trash" /></span><span data-confirm-info-icon hidden><x-ui.icon name="card" /></span></div><h2 id="confirm-title">{{ __('Please confirm') }}</h2><p id="confirm-description"></p><div class="app-dialog-actions"><button type="button" class="btn btn-secondary" data-confirm-cancel>{{ __('Cancel') }}</button><button type="button" class="btn btn-danger" data-confirm-accept>{{ __('Confirm') }}</button></div></dialog>
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables/responsive.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/js/i18n-'.(in_array(app()->getLocale(),['en','gu','hi'],true)?app()->getLocale():'en').'.js') }}" defer></script><script src="{{ asset('assets/js/admin.js') }}?v={{ filemtime(public_path('assets/js/admin.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
