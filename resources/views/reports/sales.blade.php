@extends('layouts.admin')
@section('title', __('Sales report'))
@section('page-title', __('Sales report'))
@section('content')
<x-ui.page-header :title="__('Sales report')" :description="__('Review saved invoice sales and tax for your selected period.')" :eyebrow="__('Reports')" />
@include('reports.filters', ['reportRoute' => 'reports.sales'])
<div class="app-summary-grid"><div class="app-summary-card"><span>{{ __('Invoice sales') }}</span><strong>₹{{ number_format($totalSales, 2) }}</strong></div><div class="app-summary-card"><span>{{ __('GST on invoices') }}</span><strong>₹{{ number_format($totalTax, 2) }}</strong></div><div class="app-summary-card"><span>{{ __('Invoices') }}</span><strong>{{ number_format($summary->invoice_count) }}</strong></div></div>
<x-ui.card class="app-card-table">
@if($invoices->isEmpty())<x-ui.empty-state :title="__('No invoices in this period')" :description="__('Try a different date range to see your sales.')" icon="chart" />
@else
<div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th scope="col">{{ __('Invoice') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Taxable amount') }}</th><th scope="col">GST</th><th scope="col">{{ __('Invoice total') }}</th></tr></thead><tbody>@foreach($invoices as $invoice)<tr><td class="app-table-title">{{ $invoice->invoice_no }}</td><td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td><td>{{ $invoice->customer->name ?? __('Customer unavailable') }}</td><td class="app-money">₹{{ number_format($invoice->taxable_amount, 2) }}</td><td class="app-money">₹{{ number_format($invoice->total_tax, 2) }}</td><td class="app-money">₹{{ number_format($invoice->grand_total, 2) }}</td></tr>@endforeach</tbody></table></div>
@endif
<x-ui.pagination :records="$invoices" />
</x-ui.card>
<p class="app-muted">{{ __('Sales reflect saved invoice totals, including cancelled invoices. Deleted invoices are excluded. Invoice sales are separate from cash collections.') }}</p>
@endsection
