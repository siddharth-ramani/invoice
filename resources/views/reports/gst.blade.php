@extends('layouts.admin')
@section('title', __('GST report'))
@section('page-title', __('GST report'))
@section('content')
<x-ui.page-header :title="__('GST report')" :description="__('Review recorded CGST and SGST across your invoices.')" :eyebrow="__('Reports')" />
@include('reports.filters', ['reportRoute' => 'reports.gst'])
<div class="app-summary-grid"><div class="app-summary-card"><span>CGST</span><strong>₹{{ number_format($summary->cgst, 2) }}</strong></div><div class="app-summary-card"><span>SGST</span><strong>₹{{ number_format($summary->sgst, 2) }}</strong></div><div class="app-summary-card"><span>{{ __('Total GST') }}</span><strong>₹{{ number_format($summary->tax, 2) }}</strong></div></div>
<x-ui.card class="app-card-table">
@if($invoices->isEmpty())<x-ui.empty-state :title="__('No invoices in this period')" :description="__('Try a different date range to see recorded tax.')" icon="tax" />
@else
<div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th scope="col">{{ __('Invoice') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Customer') }}</th><th scope="col">CGST</th><th scope="col">SGST</th><th scope="col">{{ __('Total GST') }}</th></tr></thead><tbody>@foreach($invoices as $invoice)<tr><td class="app-table-title">{{ $invoice->invoice_no }}</td><td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td><td>{{ $invoice->customer->name ?? __('Customer unavailable') }}</td><td class="app-money">₹{{ number_format($invoice->cgst_total, 2) }}</td><td class="app-money">₹{{ number_format($invoice->sgst_total, 2) }}</td><td class="app-money">₹{{ number_format($invoice->total_tax, 2) }}</td></tr>@endforeach</tbody></table></div>
@endif
<x-ui.pagination :records="$invoices" />
</x-ui.card>
<p class="app-muted">{{ __('These are recorded invoice tax values, including cancelled invoices. Deleted invoices are excluded.') }}</p>
@endsection
