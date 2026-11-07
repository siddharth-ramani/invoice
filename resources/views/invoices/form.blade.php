@php
    $editing = isset($invoice);
    $defaultItems = $editing ? $invoice->items->toArray() : [[]];
    if ($editing) foreach ($defaultItems as &$defaultItem) { $defaultItem['product_id'] = $invoiceProducts[$defaultItem['id']] ?? null; } unset($defaultItem);
    $rows = old('items', $defaultItems);
    $rows = is_array($rows) && count($rows) ? array_values($rows) : [[]];
    $selectedCustomer = old('customer_id', $invoice->customer_id ?? '');
@endphp
<form method="POST" action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}" id="invoiceForm" data-recalculate="{{ !$editing || old('items') !== null ? 'true' : 'false' }}">@csrf
@if($editing) @method('PUT')<input type="hidden" name="original_version" value="{{ is_string(old('original_version')) ? old('original_version') : $originalVersion }}"> @endif
@if($editing && $errors->has('original_version'))<p class="app-revision-notice"><a href="{{ route('invoices.edit', $invoice) }}">{{ __('Reload the latest saved invoice') }}</a>{{ __('. Copy any unsaved changes before reloading.') }}</p>@endif
<div class="invoice-builder-grid"><div class="invoice-builder-main">
<x-ui.card :title="__('Invoice details')" :description="__('Choose the customer and date for this invoice.')"><div class="app-form-grid">
<x-ui.field name="invoice_no" :label="__('Invoice number')" :value="$invoice->invoice_no ?? $nextInvoiceNo ?? ''" required maxlength="50" readonly :recover="!(!$editing && $errors->has('invoice_no'))" />
<x-ui.field name="invoice_date" :label="__('Invoice date')" type="date" :value="$invoice->invoice_date ?? \Carbon\Carbon::now(config('bizpilot.timezone'))->toDateString()" required />
<div class="app-span-full"><x-ui.select name="customer_id" :label="__('Customer')" required><option value="">{{ __('Choose a customer') }}</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(is_scalar($selectedCustomer) && (string) $selectedCustomer === (string) $customer->id)>{{ $customer->name }}</option>@endforeach</x-ui.select>
@if($customers->isEmpty())<p class="form-text">{{ __('No customers found.') }} <a href="{{ route('customers.create') }}">{{ __('Add a customer') }}</a> {{ __('before creating an invoice.') }}</p>@endif
</div></div></x-ui.card>
<x-ui.card :title="__('Products & services')" :description="__('Enter quantities, rates and tax for each line.')"><div id="invoiceItems" class="invoice-items">@foreach($rows as $index => $item) @include('invoices.item', ['index' => $index, 'item' => $item]) @endforeach</div><x-ui.button type="button" variant="secondary" icon="plus" id="addItem">{{ __('Add item') }}</x-ui.button><p id="invoiceItemFeedback" class="app-muted mt-2" role="status" aria-live="polite"></p></x-ui.card>
<x-ui.card :title="__('Notes')"><x-ui.field name="notes" :label="__('Invoice notes')" type="textarea" :value="$invoice->notes ?? ''" :placeholder="__('Add any details your customer should know')" rows="3" maxlength="1000" /></x-ui.card>
</div><aside class="invoice-builder-summary" aria-label="{{ __('Invoice totals') }}">
<x-ui.card :title="__('Invoice summary')"><x-ui.field name="discount_percent" :label="__('Discount (%)')" type="number" :value="$invoice->discount_percent ?? 0" min="0" :max="$editing ? 999.99 : 100" step="0.01" inputmode="decimal" />
<div class="invoice-summary-fields"><x-ui.field name="taxable_amount" :label="__('Taxable amount (₹)')" :value="$invoice->taxable_amount ?? '0.00'" readonly tabindex="-1" /><x-ui.field name="discount" :label="__('Discount (₹)')" :value="$invoice->discount ?? '0.00'" readonly tabindex="-1" /><x-ui.field name="cgst_total" :label="__('CGST (₹)')" :value="$invoice->cgst_total ?? '0.00'" readonly tabindex="-1" /><x-ui.field name="sgst_total" :label="__('SGST (₹)')" :value="$invoice->sgst_total ?? '0.00'" readonly tabindex="-1" /><x-ui.field name="round_off" :label="__('Round off (₹)')" :value="$invoice->round_off ?? '0.00'" readonly tabindex="-1" /></div>
<div class="invoice-payable"><x-ui.field name="grand_total" :label="__('Invoice total (₹)')" :value="$invoice->grand_total ?? '0.00'" readonly tabindex="-1" /></div><p class="form-text">{{ __('Totals use your existing tax and whole-rupee rounding rules. The server confirms the final amount when saved.') }}</p>
@if($editing)<p class="form-text">{{ __('Saved totals stay visible until you change the invoice inputs. Notes and descriptions do not recalculate saved amounts.') }}</p><div class="app-revision-notice"><label for="confirm-recalculation"><input type="checkbox" id="confirm-recalculation" name="confirm_recalculation" value="1" @checked(old('confirm_recalculation'))><span>{{ __('I confirm that changing quantities, rates, tax or discount recalculates this invoice. An encrypted copy of the earlier saved values will be retained.') }}</span></label><p class="form-text">{{ __('Confirmation is required only when monetary inputs change.') }}</p></div>@endif
<div class="invoice-save-actions"><x-ui.button icon="check">{{ $editing ? __('Save changes') : __('Save invoice') }}</x-ui.button><x-ui.button :href="route('invoices.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button></div>
</x-ui.card>
</aside></div>
</form>
<template id="invoiceItemTemplate">@include('invoices.item', ['index' => '__INDEX__', 'item' => []])</template>
@push('scripts')<script src="{{ asset('assets/js/invoice.js') }}?v={{ filemtime(public_path('assets/js/invoice.js')) }}" defer></script>@endpush

@push('scripts')<script src="{{ asset('assets/js/catalog.js') }}?v={{ filemtime(public_path('assets/js/catalog.js')) }}" defer></script>@endpush
