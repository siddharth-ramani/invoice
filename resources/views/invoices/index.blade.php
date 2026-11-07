@extends('layouts.admin')
@section('title', __('Invoices'))
@section('page-title', __('Invoices'))
@section('content')
<x-ui.page-header :title="__('Invoices')" :description="__('Find, review and download your business invoices.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('invoices.create')" icon="plus">{{ __('Create invoice') }}</x-ui.button></x-ui.page-header>
<x-ui.card :title="__('Download invoices')" :description="__('Choose an invoice-date range to download PDFs in a ZIP file.')"><form method="GET" action="{{ route('invoices.bulkDownload') }}" class="app-filter-grid" data-download><x-ui.field name="from_date" id="download-from-date" :label="__('From date')" type="date" required /><x-ui.field name="to_date" id="download-to-date" :label="__('To date')" type="date" required /><div class="app-actions"><x-ui.button icon="download">{{ __('Download ZIP') }}</x-ui.button></div></form></x-ui.card>
<x-ui.list-filters :action="route('invoices.index')" invoice />
<x-ui.card class="app-card-table">
@if($invoices->isEmpty())<x-ui.empty-state :title="__('No invoices found')" :description="__('Create an invoice or reset the filters to see more results.')"><x-ui.button :href="route('invoices.create')" icon="plus">{{ __('Create invoice') }}</x-ui.button></x-ui.empty-state>
@else
<div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th scope="col">{{ __('Invoice') }}</th><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Recorded status') }}</th><th scope="col">{{ __('Total') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead><tbody>
@foreach($invoices as $invoice)<tr><td class="app-table-title"><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td><td>{{ $invoice->customer->name ?? __('Customer unavailable') }}</td><td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td><td><x-dashboard.badge :status="$invoice->status" /></td><td class="app-money">₹{{ number_format($invoice->grand_total, 2) }}</td><td><div class="app-actions"><x-ui.button :href="route('invoices.show', $invoice)" variant="secondary" icon="eye" class="btn-sm">{{ __('View') }}</x-ui.button><x-ui.button :href="route('invoices.edit', $invoice)" variant="secondary" icon="edit" class="btn-sm">{{ __('Edit') }}</x-ui.button><x-ui.button :href="route('invoices.download', $invoice)" variant="secondary" icon="download" class="btn-sm">PDF</x-ui.button><x-ui.delete-form :action="route('invoices.destroy', $invoice)" :version="$invoiceVersions[$invoice->id]" message="Delete this invoice from the active list? It will be soft-deleted; historical totals will not be recalculated." /></div></td></tr>@endforeach
</tbody></table></div>
@endif
<x-ui.pagination :records="$invoices" />
</x-ui.card>
@endsection
