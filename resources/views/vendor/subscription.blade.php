@extends('layouts.admin')
@section('title', 'Subscription usage')
@section('page-title', 'Subscription')
@section('content')
<x-ui.page-header :title="__('Subscription usage')" description="See your current invoice allowance and plan dates." eyebrow="Manage"><x-ui.button :href="route('subscription')" variant="secondary" icon="card">View plans</x-ui.button></x-ui.page-header>
@if(!$subscription)
<x-ui.card><x-ui.empty-state :title="__('No active subscription')" description="Choose a plan to start creating invoices." icon="card"><x-ui.button :href="route('subscription')">Choose a plan</x-ui.button></x-ui.empty-state></x-ui.card>
@else
<div class="alert {{ $isExpired ? 'alert-warning' : 'alert-success' }} app-feedback" role="status"><x-ui.icon name="info" /><div>{{ $isExpired ? 'Your subscription has expired. Choose a plan to continue creating invoices.' : 'Your subscription is active.' }}</div></div>
<div class="app-summary-grid"><div class="app-summary-card"><span>Plan</span><strong>{{ $subscription->plan->name ?? 'Plan unavailable' }}</strong></div><div class="app-summary-card"><span>Valid until</span><strong>{{ \Carbon\Carbon::parse($subscription->end_date)->format('d M Y') }}</strong></div><div class="app-summary-card"><span>Invoices used</span><strong>{{ $subscription->invoice_used }} <small class="app-muted">/ {{ $subscription->plan->invoice_limit ?? 'Unlimited' }}</small></strong></div></div>
@endif
@endsection
