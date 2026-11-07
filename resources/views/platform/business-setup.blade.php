@extends('layouts.admin')
@section('title',__('Business setup'))
@section('page-title',__('Business setup'))
@section('content')
<x-ui.page-header :title="__('Business setup')" :description="__('Your current setup and the next steps for daily business use. Opening this page changes no records.')" :eyebrow="__('Workspace')" />
@include('platform.setup-status',['isAdmin'=>false])
<x-ui.card :title="__('Daily business flow')"><ol class="app-setup-steps"><li>{{ __('Add your customer and confirm their billing details.') }}</li><li>{{ __('Prepare a quotation when needed, then convert an accepted quotation into one invoice.') }}</li><li>{{ __('Record actual received payments and download receipts. Review old invoice balances before relying on outstanding totals.') }}</li><li>{{ __('Record actual expenses. Use inventory only for businesses that track physical stock.') }}</li><li>{{ __('Check analytics using invoice dates, payment dates and expense dates separately.') }}</li></ol><div class="app-actions"><x-ui.button :href="route('customers.index')" variant="secondary">{{ __('Customers') }}</x-ui.button><x-ui.button :href="route('invoices.index')" variant="secondary">{{ __('Invoices') }}</x-ui.button><x-ui.button :href="route('payments.index')" variant="secondary">{{ __('Payments') }}</x-ui.button></div></x-ui.card>
@endsection
