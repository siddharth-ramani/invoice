@extends('layouts.admin')
@section('title',__('Subscription plans'))
@section('page-title',__('Subscription plans'))
@section('content')
<x-ui.page-header :title="__('Subscription plans')" :description="__('Manage pricing, billing duration and invoice limits.')" :eyebrow="__('Administration')"><x-ui.button :href="route('subscription-plans.create')" icon="plus">{{ __('Add plan') }}</x-ui.button></x-ui.page-header>
<x-ui.card class="app-card-table">
@if($plans->isEmpty())<x-ui.empty-state :title="__('No plans configured')" :description="__('Create a plan to make it available to businesses.')" icon="card"><x-ui.button :href="route('subscription-plans.create')" icon="plus">{{ __('Add plan') }}</x-ui.button></x-ui.empty-state>
@else
<div class="app-table-scroll"><table class="app-data-table"><thead><tr><th>#</th><th>{{ __('Plan') }}</th><th>{{ __('Price') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Invoice limit') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@foreach($plans as $plan)<tr><td>{{ $loop->iteration }}</td><td class="app-table-title">{{ $plan->name }}<span class="app-table-stack app-muted">{{ __('Assigned subscriptions') }}: {{ $plan->subscriptions_count }}</span></td><td class="app-money">₹{{ number_format($plan->price,2) }}</td><td>{{ $plan->duration_days }} {{ __('days') }}</td><td>{{ $plan->invoice_limit??__('Unlimited') }}</td><td><span class="bp-badge {{ $plan->status?'bp-badge-paid':'bp-badge-unknown' }}">{{ $plan->status?__('Active'):__('Inactive') }}</span></td><td><div class="app-actions"><x-ui.button :href="route('subscription-plans.edit',$plan)" variant="secondary" icon="edit" class="btn-sm">{{ __('Edit') }}</x-ui.button>@if($plan->status)<form method="POST" action="{{ route('subscription-plans.archive',$plan) }}" data-confirm="{{ __('Make this plan inactive? Existing subscriptions and records are retained.') }}">@csrf<input type="hidden" name="original_version" value="{{ app(\App\Services\Business\PlanCatalog::class)->version($plan) }}"><x-ui.button variant="secondary" class="btn-sm">{{ __('Make inactive') }}</x-ui.button></form>@endif</div></td></tr>@endforeach
</tbody></table></div>
@endif
</x-ui.card>
@endsection
