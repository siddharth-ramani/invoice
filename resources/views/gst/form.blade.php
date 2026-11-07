@php $editing = isset($gst); @endphp
<form method="POST" action="{{ $editing ? route('gst.update', $gst) : route('gst.store') }}" class="app-form-width">@csrf
@if($editing) @method('PUT') @endif
<x-ui.card :title="__('Tax rate details')" :description="__('Changes to saved rates do not recalculate existing invoices.')"><div class="app-form-grid"><x-ui.field name="name" :label="__('Rate name')" :value="$gst->name ?? ''" :placeholder="__('For example, GST 18%')" required maxlength="50" /><x-ui.field name="rate" :label="__('GST rate (%)')" type="number" :value="$gst->rate ?? ''" placeholder="18.00" required min="0" max="100" step="0.01" inputmode="decimal" /></div><div class="app-form-footer"><x-ui.button :href="route('gst.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button><x-ui.button icon="check">{{ $editing ? __('Save changes') : __('Add GST rate') }}</x-ui.button></div></x-ui.card>
</form>
