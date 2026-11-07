<x-ui.field name="amount" :label="__('Payment amount (₹)')" type="number" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" required data-payment-amount />
<x-ui.field name="payment_date" :label="__('Payment date')" type="date" :value="now(config('bizpilot.timezone'))->toDateString()" :max="now(config('bizpilot.timezone'))->toDateString()" required />
<x-ui.select name="method" :label="__('Payment method')" required>@foreach(['cash'=>__('Cash'),'bank_transfer'=>__('Bank transfer'),'upi'=>'UPI','cheque'=>__('Cheque'),'card'=>__('Card'),'other'=>__('Other')] as $value=>$label)<option value="{{ $value }}" @selected(old('method','cash')===$value)>{{ $label }}</option>@endforeach</x-ui.select>
<x-ui.field name="reference" :label="__('Reference / transaction number')" maxlength="100" :placeholder="__('Optional bank or receipt reference')" />
<div class="app-span-full"><x-ui.field name="notes" :label="__('Payment notes')" type="textarea" maxlength="1000" rows="2" /></div>
