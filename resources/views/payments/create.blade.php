@extends('layouts.admin')
@section('title',__('Record payment'))
@section('page-title',__('Payments'))
@section('content')
@php
    $draftAllocations = [];
    foreach (is_array(old('allocations')) ? old('allocations') : [] as $entry) {
        if (is_array($entry) && is_scalar($entry['invoice_id'] ?? null) && is_scalar($entry['amount'] ?? null)) {
            $draftAllocations[(int)$entry['invoice_id']] = $entry['amount'];
        }
    }
@endphp
<x-ui.page-header :title="__('Record payment')" :description="__('Allocate a receipt to one or more invoices for the same customer.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('payments.index')" variant="secondary" icon="back">{{ __('Back to payments') }}</x-ui.button></x-ui.page-header>
<x-ui.card :title="__('Choose customer')"><form method="GET" action="{{ route('payments.create') }}" class="app-filter-grid"><x-ui.select name="customer_id" :label="__('Customer')" required><option value="">{{ __('Choose customer') }}</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId===$c->id)>{{ $c->name }}</option>@endforeach</x-ui.select><x-ui.field name="q" :label="__('Invoice search')" :value="is_string(request('q')) ? request('q') : ''" :placeholder="__('Invoice number (optional)')" maxlength="100" /><div class="app-actions"><x-ui.button icon="search">{{ __('Load invoices') }}</x-ui.button></div></form></x-ui.card>
@if($customerId && $invoices->isNotEmpty())
<form method="POST" action="{{ route('payments.store') }}" data-payment-form>@csrf<input type="hidden" name="customer_id" value="{{ $customerId }}"><input type="hidden" name="idempotency_key" value="{{ is_string(old('idempotency_key')) ? old('idempotency_key') : $idempotencyKey }}">
<x-ui.card :title="__('Payment details')"><div class="app-form-grid">@include('payments.fields')</div></x-ui.card>
<x-ui.card :title="__('Allocate payment')" :description="__('Enter zero for invoices you are not paying. Allocation total must exactly match the payment.')"><p class="form-text">{{ __('Up to 100 invoices are shown. Use invoice search to narrow the list. Invoices awaiting review can receive actual prior payments; their balances stay excluded until review is confirmed.') }}</p><div class="payment-allocation-list">@foreach($invoices as $i=>$row)<div class="payment-allocation-row"><div><a href="{{ route('invoices.show',$row->id) }}">{{ $row->invoice_no }}</a><p class="form-text">Unallocated: ₹{{ number_format($row->outstanding_cents/100,2) }} · {{ $row->confirmed_at ? __('Reviewed') : __('Review in progress') }}</p></div><input type="hidden" name="allocations[{{ $i }}][invoice_id]" value="{{ $row->id }}"><x-ui.field :name="'allocations['.$i.'][amount]'" :label="__('Allocate (₹)')" type="number" :value="$draftAllocations[$row->id] ?? '0.00'" :recover="false" min="0" :max="\App\Services\Payments\Money::decimal((int)$row->outstanding_cents)" step="0.01" inputmode="decimal" data-allocation-amount /></div>@endforeach</div><p class="payment-allocation-total" role="status" aria-live="polite">{{ __('Allocated: ₹') }}<output data-allocation-total>0.00</output></p><div class="app-actions"><x-ui.button icon="check">{{ __('Save payment') }}</x-ui.button><x-ui.button :href="route('payments.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button></div></x-ui.card></form>
@elseif($customerId)<x-ui.card><x-ui.empty-state :title="__('No eligible invoices')" :description="__('Search another number, or begin the payment review from an invoice. Fully allocated invoices cannot receive another payment.')" /><x-ui.button :href="route('receivables.index',['balance'=>'review','customer_id'=>$customerId])" variant="secondary">{{ __('Review invoices') }}</x-ui.button></x-ui.card>@endif
@endsection
@push('scripts')<script src="{{ asset('assets/js/payments.js') }}?v={{ filemtime(public_path('assets/js/payments.js')) }}" defer></script>@endpush
