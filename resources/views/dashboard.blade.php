@extends('layouts.admin')
@section('title', __('Overview'))
@section('page-title', __('Overview'))
@section('content')
<div class="bizpilot-dashboard">
    <header class="bp-page-header">
        <div><p class="bp-eyebrow">{{ $isAdmin ? __('Platform overview') : __('Your business at a glance') }}</p><h1>{{ __('Business overview') }}</h1><p class="bp-subtitle">{{ __('A clearer view of your invoices and sales.') }}</p></div>
        <div class="bp-header-actions">
            <a href="{{ route('customers.create') }}" class="bp-button bp-button-secondary"><x-dashboard.icon name="people" />{{ $isAdmin ? __('Add vendor') : __('Add customer') }}</a>
            @unless($isAdmin)<a href="{{ route('invoices.create') }}" class="bp-button bp-button-primary"><x-dashboard.icon name="plus" />{{ __('Create invoice') }}</a>@endunless
        </div>
    </header>

    <form method="GET" action="{{ route('dashboard') }}" class="bp-filters" aria-label="{{ __('Dashboard date filters') }}">
        <div class="bp-filter-caption"><x-dashboard.icon name="calendar" /><span>{{ __('Reporting period') }}</span></div>
        <div class="bp-field"><label for="from_date">{{ __('From date') }}</label><input id="from_date" type="date" name="from_date" value="{{ is_string(old('from_date')) ? old('from_date') : $from->toDateString() }}" required @error('from_date') aria-invalid="true" aria-describedby="from_date-error" @enderror>@error('from_date')<span class="bp-field-error" id="from_date-error">{{ $message }}</span>@enderror</div>
        <div class="bp-field"><label for="to_date">{{ __('To date') }}</label><input id="to_date" type="date" name="to_date" value="{{ is_string(old('to_date')) ? old('to_date') : $to->toDateString() }}" required @error('to_date') aria-invalid="true" aria-describedby="to_date-error" @enderror>@error('to_date')<span class="bp-field-error" id="to_date-error">{{ $message }}</span>@enderror</div>
        <div class="bp-filter-actions"><button class="bp-button bp-button-primary" type="submit">{{ __('Apply filters') }}</button><a class="bp-reset" href="{{ route('dashboard') }}">{{ __('Reset') }}</a></div>
    </form>
    <div class="bp-period"><span>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span><span>{{ $isAdmin ? __('All businesses') : __('Your business') }} · Invoice date</span></div>

    <div class="bp-metrics">
        <x-dashboard.metric :label="__('Invoice sales')" :value="'₹'.number_format($summary->sales, 2)" hint="Invoice value, including tax" icon="rupee" accent="blue" />
        <x-dashboard.metric :label="__('Total invoices')" :value="number_format($summary->invoice_count)" hint="In the selected period" icon="document" accent="violet" />
        <x-dashboard.metric :label="__('GST on invoices')" :value="'₹'.number_format($summary->tax, 2)" hint="Recorded CGST and SGST" icon="tax" accent="teal" />
        <x-dashboard.metric :label="__('Customers invoiced')" :value="number_format($summary->customer_count)" hint="Distinct customers in this period" icon="people" accent="amber" />
    </div>

    <div class="bp-dashboard-grid">
        <x-dashboard.panel :title="__('Monthly sales')" :description="__('Invoice value by month, including tax')" class="bp-sales-panel">
            <x-slot:action><span class="bp-chart-legend"><span></span>{{ __('Invoice sales (₹)') }}</span></x-slot:action>
            @if($summary->invoice_count)<x-dashboard.chart :months="$months" />@else<x-dashboard.empty :title="__('No invoices in this period')" :description="__('Choose another date range to see your sales trend.')">@unless($isAdmin)<a class="bp-button bp-button-primary" href="{{ route('invoices.create') }}">{{ __('Create an invoice') }}</a>@endunless</x-dashboard.empty>@endif
        </x-dashboard.panel>
        <x-dashboard.panel :title="__('Top customers')" :description="__('Ranked by invoice sales')">
            @if($topCustomers->isEmpty())<x-dashboard.empty :title="__('Your customers will appear here')" :description="__('Save an invoice to start seeing customer sales.')" />@else
            <ol class="bp-customer-list">@foreach($topCustomers as $customer)<li><span class="bp-customer-rank">{{ $loop->iteration }}</span><div class="bp-customer-info"><h3>{{ $customer->customer->name ?? __('Customer unavailable') }}</h3><p>{{ $customer->invoice_count }} {{ $customer->invoice_count == 1 ? 'invoice' : 'invoices' }}</p></div><strong>₹{{ number_format($customer->sales, 2) }}</strong></li>@endforeach</ol>
            @endif
        </x-dashboard.panel>
    </div>

    <x-dashboard.panel :title="__('Recent invoices')" :description="__('Latest invoice dates in your selected period')">
        <x-slot:action>
            @unless($isAdmin)
<a class="bp-text-link" href="{{ route('invoices.index') }}">{{ __('View invoices') }} <x-dashboard.icon name="arrow" /></a>
            @endunless
        </x-slot:action>
        @if($recentInvoices->isEmpty())<x-dashboard.empty :title="__('No invoices to show')" :description="__('Try a different period, or create an invoice to get started.')" />@else
        <div class="bp-table-scroll" tabindex="0" role="region" aria-label="{{ __('Recent invoices') }}"><table class="bp-table bp-invoice-table"><thead><tr><th scope="col">{{ __('Invoice') }}</th><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Recorded status') }}</th><th scope="col" class="bp-number">{{ __('Amount') }}</th>@unless($isAdmin)<th scope="col" class="bp-number">{{ __('Action') }}</th>@endunless</tr></thead><tbody>
            @foreach($recentInvoices as $invoice)<tr><th scope="row">{{ $invoice->invoice_no }}</th><td data-label="Customer">{{ $invoice->customer->name ?? __('Customer unavailable') }}</td><td class="bp-nowrap" data-label="Date">{{ \Carbon\CarbonImmutable::parse($invoice->invoice_date)->format('d M Y') }}</td><td data-label="Recorded status"><x-dashboard.badge :status="$invoice->status" /></td><td class="bp-number bp-nowrap" data-label="Amount">₹{{ number_format($invoice->grand_total, 2) }}</td>@unless($isAdmin)<td class="bp-number"><a href="{{ route('invoices.download', $invoice->id) }}" class="bp-download" aria-label="Download invoice {{ $invoice->invoice_no }} PDF"><x-dashboard.icon name="download" />PDF</a></td>@endunless</tr>@endforeach
        </tbody></table></div>
        <p class="bp-table-note">{{ $paymentTracking ? __('Recorded statuses are original invoice labels. Reviewed balances and collections are shown separately.') : __('Recorded statuses are invoice labels. Payment collections and balances are not tracked yet.') }}</p>
        @endif
    </x-dashboard.panel>

    @include('payments.dashboard')
    <section class="bp-availability" aria-labelledby="financial-activity-heading"><div><p class="bp-eyebrow">{{ __('Financial activity') }}</p><h2 id="financial-activity-heading">{{ __('More clarity as you record more') }}</h2><p>{{ __('These figures will be available when their transactions are tracked.') }}</p></div><dl><div><dt>{{ __('Collections & recent payments') }}</dt><dd>@if($paymentTracking)<a href="{{ route('payments.index') }}">{{ __('View collections') }}</a>@else Payment records needed @endif</dd></div><div><dt>{{ __('Outstanding & overdue') }}</dt><dd>@if($paymentTracking)<a href="{{ route('receivables.index') }}">{{ __('View reviewed balances') }}</a>@else Allocations and due dates needed @endif</dd></div><div><dt>{{ __('Expenses & estimated profit') }}</dt><dd>{{ __('Expense and cost records needed') }}</dd></div></dl></section>
    <footer class="bp-dashboard-footer"><p>{{ __('Sales reflect saved invoice totals, including invoices marked cancelled. Deleted invoices are excluded.') }}</p>@if($isAdmin)<p>{{ number_format($accounts->vendors ?? 0) }} vendor accounts · {{ number_format($accounts->customers ?? 0) }} customer accounts · All time</p>@endif</footer>
</div>
@endsection
