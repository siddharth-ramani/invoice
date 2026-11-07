@php
$editing=isset($subscriptionPlan);$planDefaults=$subscriptionPlan??null;
$statusInput=old('status',$editing?($planDefaults->status??1):0);$statusValue=is_scalar($statusInput)?(string)$statusInput:'0';$locked=$termsLocked??false;
@endphp
<form method="POST" action="{{ $editing ? route('subscription-plans.update',$subscriptionPlan) : route('subscription-plans.store') }}" class="app-form-width">@csrf
@if($editing)@method('PUT')<input type="hidden" name="original_version" value="{{ $planVersion }}">@endif
<x-ui.card :title="__('Plan configuration')" :description="__('Pricing and limits are stored with this plan.')">
@if($locked)<p class="app-feedback">{{ __('This plan has subscription history. Price, duration and invoice limit are locked to protect existing businesses.') }}</p>@endif
<div class="app-form-grid">
<x-ui.field name="name" :label="__('Plan name')" :value="$planDefaults->name??''" :placeholder="__('For example, Starter')" required maxlength="255" />
<x-ui.field name="price" type="number" :label="__('Plan price (₹)')" :value="$planDefaults->price??''" :readonly="$locked" required min="0" max="99999999.99" step="0.01" inputmode="decimal" />
<x-ui.field name="duration_days" type="number" :label="__('Duration (days)')" :value="$planDefaults->duration_days??30" :readonly="$locked" required min="1" max="36500" step="1" />
<x-ui.field name="invoice_limit" type="number" :label="__('Invoice limit')" :value="$planDefaults->invoice_limit??''" :readonly="$locked" min="0" max="2147483647" step="1" :help="__('Leave blank for unlimited invoices. Zero means no new invoices.')" />
<x-ui.select name="status" :label="__('Availability')" required><option value="1" @selected($statusValue==='1')>{{ __('Active — available to businesses') }}</option><option value="0" @selected($statusValue==='0')>{{ __('Inactive — hidden from new selections') }}</option></x-ui.select>
</div>
<p class="form-text">{{ __('Manual billing only. The plan price does not automatically charge a vendor or create a payment receipt.') }}</p>
</x-ui.card>
@if(app(\App\Services\Business\Platform::class)->ready())
<x-ui.card :title="__('Included modules')" :description="__('Access changes apply to businesses assigned to this plan. Existing financial records and used invoice quota are retained.')"><div class="app-form-grid">
@foreach(\App\Services\Business\BusinessSetup::FEATURES as $key=>$label)
<label class="payment-acknowledgement"><input type="hidden" name="features[{{ $key }}]" value="0"><input type="checkbox" name="features[{{ $key }}]" value="1" @checked(old('features.'.$key,$featureAccess[$key]??true))><span>{{ __($label) }}</span></label>
@endforeach
</div><p class="form-text">{{ __('Inventory access does not enable stock tracking automatically. Each business chooses whether to use inventory.') }}</p></x-ui.card>
@endif
<div class="app-form-footer"><x-ui.button :href="route('subscription-plans.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button><x-ui.button icon="check">{{ $editing?__('Save changes'):__('Create plan') }}</x-ui.button></div></form>
