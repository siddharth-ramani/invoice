<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\InvoiceReceivable;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\Invoicing\InvoiceVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentWriter
{
    private function vendor(User $actor): User
    {
        abort_unless($actor->role === 'vendor', 403);
        app(PaymentModule::class)->requireReady();

        return User::whereKey($actor->id)->where('role', 'vendor')->lockForUpdate()->firstOrFail();
    }

    public function paid(int $invoiceId): int
    {
        $total = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->where('a.invoice_id', $invoiceId)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_reversals as r')->whereColumn('r.payment_id', 'p.id'))->sum(DB::raw('ROUND(a.amount * 100, 0)'));

        return (int) $total;
    }

    public function review(User $actor, Invoice $invoice, array $data): InvoiceReceivable
    {
        return DB::transaction(function () use ($actor, $invoice, $data) {
            $this->vendor($actor);
            $saved = Invoice::whereKey($invoice->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals(app(InvoiceVersion::class)->token($saved), $data['original_version'])) {
                $this->fail('original_version', 'Invoice changed. Reload it before reviewing payments.');
            }
            if ($saved->status === 'cancelled' || Money::cents($saved->grand_total) <= 0) {
                $this->fail('review', 'Only a positive, active invoice can enter payment tracking.');
            }
            $row = InvoiceReceivable::where('invoice_id', $saved->id)->lockForUpdate()->first();
            if ($row?->confirmed_at) {
                $this->fail('review', 'This invoice has already been reviewed. Its payment history remains available.');
            }
            if (! empty($data['due_date']) && $data['due_date'] < $saved->invoice_date) {
                $this->fail('due_date', 'Due date cannot be before invoice date.');
            }
            if (! $row) {
                $row = InvoiceReceivable::create(['invoice_id' => $saved->id, 'vendor_id' => $actor->id, 'customer_id' => $saved->customer_id, 'original_total' => $saved->grand_total, 'legacy_status' => $saved->status, 'due_date' => $data['due_date'] ?? null, 'reviewed_by' => $actor->id, 'admin_actor_id' => session('bizpilot.impersonation.admin_id'), 'review_note' => $data['review_note']]);
            }
            if ($data['action'] === 'confirm') {
                if ($row->legacy_status === 'paid' && $this->paid($saved->id) !== Money::cents($row->original_total)) {
                    $this->fail('review', 'This invoice was marked paid. Record its actual prior payments in full before confirming the balance. No opening payment is generated.');
                }
                $row->update(['confirmed_at' => now(), 'reviewed_by' => $actor->id, 'review_note' => $data['review_note'], 'due_date' => $data['due_date'] ?? null]);
            }

            return $row;
        }, 5);
    }

    public function create(User $actor, array $data): array
    {
        // Canonical content binds a retry key to exactly one financial request.
        $allocations = [];
        foreach ($data['allocations'] as $entry) {
            $cents = Money::cents($entry['amount'] ?? 0);
            if ($cents > 0) {
                $id = (int) $entry['invoice_id'];
                if (isset($allocations[$id])) {
                    $this->fail('allocations', 'Allocate each invoice only once.');
                }$allocations[$id] = $cents;
            }
        }
        ksort($allocations, SORT_NUMERIC);
        $amount = Money::cents($data['amount']);
        if ($amount <= 0 || array_sum($allocations) !== $amount || count($allocations) > 100) {
            $this->fail('allocations', 'Allocate the exact payment amount across one or more invoices. Advances and unallocated payments are not supported.');
        }
        $canonical = [(int) $data['customer_id'], $data['payment_date'], $amount, $data['method'], (string) ($data['reference'] ?? ''), (string) ($data['notes'] ?? ''), $allocations];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $data, $allocations, $amount, $hash) {
            $vendor = $this->vendor($actor);
            $existing = Payment::where('vendor_id', $actor->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    $this->fail('idempotency_key', 'This submission key was already used for a different payment. Open a new payment form.');
                }

                return ['payment' => $existing, 'replayed' => true];
            }
            $customer = User::whereKey($data['customer_id'])->where('role', 'customer')->where('created_by', $actor->id)->lockForUpdate()->first();
            if (! $customer) {
                $this->fail('customer_id', 'Choose your own customer.');
            }
            $lines = [];
            foreach ($allocations as $id => $cents) {
                $invoice = Invoice::whereKey($id)->where('vendor_id', $actor->id)->where('customer_id', $customer->id)->lockForUpdate()->first();
                if (! $invoice || $invoice->status === 'cancelled') {
                    $this->fail('allocations', 'An invoice is unavailable or does not belong to this customer.');
                }
                $receivable = InvoiceReceivable::where('invoice_id', $id)->where('vendor_id', $actor->id)->where('customer_id', $customer->id)->lockForUpdate()->first();
                if (! $receivable) {
                    $this->fail('allocations', 'Begin the invoice payment review before recording its prior payments.');
                }
                if (Money::cents($invoice->grand_total) !== Money::cents($receivable->original_total)) {
                    $this->fail('allocations', 'Invoice total differs from its reviewed total. Resolve this before taking payment.');
                }
                if ($data['payment_date'] < $invoice->invoice_date) {
                    $this->fail('payment_date', 'Payment date cannot be earlier than an allocated invoice.');
                }
                if ($cents > Money::cents($receivable->original_total) - $this->paid($id)) {
                    $this->fail('allocations', 'An allocation exceeds the current unpaid amount. Refresh the invoices and review your payment.');
                }
                $lines[] = ['invoice_id' => $id, 'amount' => Money::decimal($cents), 'invoice_no' => $invoice->invoice_no, 'invoice_date' => $invoice->invoice_date];
            }
            $sequence = (int) Payment::where('vendor_id', $actor->id)->max('receipt_sequence') + 1;
            $payment = Payment::create(['vendor_id' => $actor->id, 'customer_id' => $customer->id, 'receipt_sequence' => $sequence, 'receipt_no' => 'RCPT/'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT), 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'payment_date' => $data['payment_date'], 'amount' => Money::decimal($amount), 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'admin_actor_id' => session('bizpilot.impersonation.admin_id'), 'party_snapshot' => ['business' => ['name' => $vendor->company_name ?: $vendor->name, 'address' => $vendor->address, 'gst_number' => $vendor->gst_number, 'mobile' => $vendor->mobile], 'customer' => ['name' => $customer->name, 'address' => $customer->address, 'gst_number' => $customer->gst_number, 'mobile' => $customer->mobile]]]);
            $payment->allocations()->createMany($lines);

            return ['payment' => $payment, 'replayed' => false];
        }, 5);
    }

    public function reverse(User $actor, Payment $payment, array $data): PaymentReversal
    {
        return DB::transaction(function () use ($actor, $payment, $data) {
            $this->vendor($actor);
            $saved = Payment::whereKey($payment->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($saved->reversal()->exists()) {
                $this->fail('reversal', 'This receipt is already reversed. No further change was made.');
            }
            if ($data['reversal_date'] < $saved->payment_date) {
                $this->fail('reversal_date', 'Reversal date cannot be earlier than payment date.');
            }

            return PaymentReversal::create(['vendor_id' => $actor->id, 'payment_id' => $saved->id, 'reversal_date' => $data['reversal_date'], 'reason' => $data['reason'], 'admin_actor_id' => session('bizpilot.impersonation.admin_id')]);
        }, 5);
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
