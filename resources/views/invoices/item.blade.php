@php $item = is_array($item) ? $item : []; @endphp
<fieldset class="invoice-item" data-invoice-item><legend class="visually-hidden">Invoice item {{ $index === '__INDEX__' ? '' : $index + 1 }}</legend><div class="invoice-item-header"><strong>{{ __('Item') }} <span data-item-number>{{ $index === '__INDEX__' ? '' : $index + 1 }}</span></strong><button type="button" class="btn btn-danger btn-sm" data-remove-item aria-label="{{ __('Remove item') }}"><x-ui.icon name="trash" /><span>{{ __('Remove') }}</span></button></div>
<div class="invoice-item-fields">
@include('products.picker')
<div class="invoice-item-name"><x-ui.field :name="'items['.$index.'][item_name]'" :label="__('Product or service')" :value="$item['item_name'] ?? ''" :placeholder="__('What are you billing for?')" required maxlength="255" /></div>
<div class="invoice-item-hsn"><x-ui.field :name="'items['.$index.'][hsn_sac]'" label="HSN / SAC" :value="$item['hsn_sac'] ?? ''" :placeholder="__('Optional')" maxlength="20" /></div>
<x-ui.field :name="'items['.$index.'][qty]'" :label="__('Quantity (saved item unit; kg for manual)')" type="number" :value="$item['qty'] ?? 1" step="0.01" :min="$editing ? 0 : 0.01" :max="$editing ? 99999999.99 : 100000" inputmode="decimal" required />
<x-ui.field :name="'items['.$index.'][rate]'" :label="__('Rate per item unit (₹)')" type="number" :value="$item['rate'] ?? ''" step="0.01" min="0" :max="$editing ? 99999999.99 : 10000000" inputmode="decimal" required />
<div class="invoice-item-tax"><x-ui.field :name="'items['.$index.'][cgst_percent]'" :label="__('CGST (%)')" type="number" :value="$item['cgst_percent'] ?? 0" step="0.01" min="0" :max="$editing ? 999.99 : 28" inputmode="decimal" required /></div>
<div class="invoice-item-tax"><x-ui.field :name="'items['.$index.'][sgst_percent]'" :label="__('SGST (%)')" type="number" :value="$item['sgst_percent'] ?? 0" step="0.01" min="0" :max="$editing ? 999.99 : 28" inputmode="decimal" required /></div>
<div class="invoice-item-total"><x-ui.field :name="'items['.$index.'][line_total]'" :label="__('Line total (₹)')" :value="$item['line_total'] ?? ''" readonly tabindex="-1" /></div>
</div><input type="hidden" name="items[{{ $index }}][sr_no]" value="{{ $index === '__INDEX__' ? '' : $index + 1 }}" data-item-serial>
</fieldset>
