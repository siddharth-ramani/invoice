@php $editing = isset($customer); $label = auth()->user()->role === 'admin' ? 'vendor' : 'customer'; @endphp
<form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="app-form-width">@csrf
@if($editing) @method('PUT') @endif
<x-ui.card :title="ucfirst($label).' information'" :description="__('Use the details you want to appear on invoices.')">
<div class="app-form-grid">
<x-ui.field name="name" :label="ucfirst($label).' name'" :value="$customer->name ?? ''" :placeholder="__('Business or contact name')" required autocomplete="organization" maxlength="255" />
<x-ui.field name="email" :label="__('Email address')" type="email" :value="$customer->email ?? ''" :placeholder="__('name@business.com')" autocomplete="email" maxlength="255" :required="!$editing && $label === 'vendor'" />
<x-ui.field name="mobile" :label="__('Mobile number')" type="tel" :value="$customer->mobile ?? ''" :placeholder="__('Contact number')" autocomplete="tel" maxlength="15" />
<x-ui.field name="gst_number" :label="__('GSTIN')" :value="$customer->gst_number ?? ''" :placeholder="__('15-character GST number')" maxlength="15" :help="__('Optional for businesses without GST registration.')" />
<div class="app-span-full"><x-ui.field name="address" :label="__('Billing address')" type="textarea" :value="$customer->address ?? ''" rows="3" :placeholder="__('Street, city, state and PIN code')" autocomplete="street-address" /></div>
</div>
</x-ui.card>
@if($editing || $label === 'vendor')
<x-ui.card :title="__('Account access')" :description="$editing ? __('Leave the password blank to keep current access unchanged.') : __('Set a password for the vendor to sign in.')"><div class="app-form-grid"><x-ui.field name="password" :label="$editing ? __('New password') : __('Password')" type="password" minlength="8" autocomplete="new-password" :required="!$editing" /><x-ui.field name="password_confirmation" :label="__('Confirm password')" type="password" minlength="8" autocomplete="new-password" :required="!$editing" /></div></x-ui.card>
@endif
<div class="app-form-footer"><span class="app-form-note">{{ __('Fields marked * are required.') }}</span><x-ui.button :href="route('customers.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button><x-ui.button icon="check">{{ $editing ? __('Save changes') : 'Add '.$label }}</x-ui.button></div>
</form>
