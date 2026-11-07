@if($paymentTracking)
<section aria-label="{{ __('Payments and receivables') }}">
    <div class="app-summary-grid">
        <div class="app-summary-card"><span>{{ __('Net collections · selected period') }}</span><strong>₹{{ number_format($collections['net']/100,2) }}</strong></div>
        <div class="app-summary-card"><span>{{ __('Reviewed outstanding · all dates') }}</span><strong>₹{{ number_format($receivables['outstanding']/100,2) }}</strong></div>
        <div class="app-summary-card"><span>{{ __('Overdue · all dates') }}</span><strong>₹{{ number_format($receivables['overdue']/100,2) }}</strong></div>
    </div>
    <p class="bp-table-note">{{ $receivables['pending'] }} invoices await payment review and are excluded from balances. Net collections use receipt dates minus reversal dates; sales remain separate.</p>
</section>
<x-dashboard.panel :title="__('Recent payments')" :description="__('Recorded receipts in the selected period')">
    <x-slot:action>
        <a class="bp-text-link" href="{{ route('payments.index') }}">{{ __('All collections') }} <x-dashboard.icon name="arrow" /></a>
    </x-slot:action>
    @if($recentPayments->isEmpty())
        <x-dashboard.empty :title="__('No payments in this period')" :description="__('Record actual receipts from an invoice to see collections.')" />
    @else
        <div class="bp-table-scroll"><table class="bp-table"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
            @foreach($recentPayments as $payment)
                <tr><td><a href="{{ route('payments.show',$payment) }}">{{ $payment->receipt_no }}</a></td><td>{{ $payment->party_snapshot['customer']['name'] }}</td><td>{{ $payment->payment_date }}</td><td class="bp-nowrap">₹{{ number_format($payment->amount,2) }}</td><td>{{ $payment->reversal ? __('Reversed') : __('Recorded') }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif
</x-dashboard.panel>
@endif
