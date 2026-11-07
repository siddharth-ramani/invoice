<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentQueries
{
    public function balances(int $vendor, ?int $customer = null)
    {
        $paid = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->where('p.vendor_id', $vendor)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_reversals as r')->whereColumn('r.payment_id', 'p.id'))->groupBy('a.invoice_id')->selectRaw('a.invoice_id, SUM(ROUND(a.amount * 100, 0)) AS paid_cents');

        return DB::table('invoices as i')->join('users as c', 'c.id', '=', 'i.customer_id')->leftJoin('invoice_receivables as r', 'r.invoice_id', '=', 'i.id')->leftJoinSub($paid, 'allocated', 'allocated.invoice_id', '=', 'i.id')->where('i.vendor_id', $vendor)->when($customer, fn ($q) => $q->where('i.customer_id', $customer))->whereNull('i.deleted_at')->where('i.status', '<>', 'cancelled')->selectRaw('i.id, i.invoice_no, i.invoice_date, i.customer_id, i.grand_total, i.status AS legacy_status, c.name AS customer_name, r.id AS review_id, r.confirmed_at, r.due_date, COALESCE(allocated.paid_cents,0) AS paid_cents, ROUND(i.grand_total * 100,0) - COALESCE(allocated.paid_cents,0) AS outstanding_cents');
    }

    public function confirmedTotals(int $vendor, ?int $customer = null): array
    {
        $q = DB::query()->fromSub($this->balances($vendor, $customer), 'b')->whereNotNull('confirmed_at');
        $s = $q->selectRaw('COALESCE(SUM(outstanding_cents),0) AS outstanding, COALESCE(SUM(CASE WHEN due_date < ? AND outstanding_cents > 0 THEN outstanding_cents ELSE 0 END),0) AS overdue', [now(config('bizpilot.timezone'))->toDateString()])->first();

        return ['outstanding' => (int) $s->outstanding, 'overdue' => (int) $s->overdue, 'pending' => $this->balances($vendor, $customer)->whereNull('r.confirmed_at')->count('i.id')];
    }

    public function payments(int $vendor, array $filters = [], bool $dates = true)
    {
        return Payment::where('vendor_id', $vendor)->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method))->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($q) => $q->where('receipt_no', 'like', '%'.$text.'%')->orWhere('reference', 'like', '%'.$text.'%')->orWhereHas('customer', fn ($q) => $q->where('created_by', $vendor)->where('name', 'like', '%'.$text.'%'))))->when($dates && ! empty($filters['from_date']), fn ($q) => $q->where('payment_date', '>=', $filters['from_date']))->when($dates && ! empty($filters['to_date']), fn ($q) => $q->where('payment_date', '<=', $filters['to_date']));
    }

    public function collections(int $vendor, array $filters): array
    {
        $gross = (int) $this->payments($vendor, $filters)->sum(DB::raw('ROUND(amount * 100,0)'));
        $reversed = (int) $this->reversals($vendor, $filters)->sum(DB::raw('ROUND(amount * 100,0)'));

        return ['gross' => $gross, 'reversed' => $reversed, 'net' => $gross - $reversed];
    }

    public function reversals(int $vendor, array $filters)
    {
        return $this->payments($vendor, $filters, false)->whereHas('reversal', function ($q) use ($filters) {
            if (! empty($filters['from_date'])) {
                $q->where('reversal_date', '>=', $filters['from_date']);
            }if (! empty($filters['to_date'])) {
                $q->where('reversal_date', '<=', $filters['to_date']);
            }
        });
    }

    public function ledger(int $vendor, int $customer, array $filters = [])
    {
        $invoices = DB::table('invoice_receivables as r')->join('invoices as i', 'i.id', '=', 'r.invoice_id')->whereNull('i.deleted_at')->where('r.vendor_id', $vendor)->where('r.customer_id', $customer)->whereNotNull('r.confirmed_at')->selectRaw("i.invoice_date AS event_date, 'Invoice' AS kind, i.invoice_no AS reference, ROUND(r.original_total*100,0) AS debit, 0 AS credit, i.id AS event_id, i.id AS row_id, 1 AS type_order");
        $base = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->join('invoice_receivables as r', 'r.invoice_id', '=', 'a.invoice_id')->where('p.vendor_id', $vendor)->where('p.customer_id', $customer)->where('r.vendor_id', $vendor)->whereNotNull('r.confirmed_at');
        $credits = (clone $base)->selectRaw("p.payment_date AS event_date, 'Payment allocation' AS kind, p.receipt_no AS reference, 0 AS debit, ROUND(a.amount*100,0) AS credit, p.id AS event_id, a.id AS row_id, 2 AS type_order");
        $reversals = (clone $base)->join('payment_reversals as v', 'v.payment_id', '=', 'p.id')->selectRaw("v.reversal_date AS event_date, 'Payment reversal' AS kind, p.receipt_no AS reference, ROUND(a.amount*100,0) AS debit, 0 AS credit, p.id AS event_id, a.id AS row_id, 3 AS type_order");
        $events = $invoices->unionAll($credits)->unionAll($reversals);
        $running = DB::query()->fromSub($events, 'events')->selectRaw('events.*, SUM(debit-credit) OVER (ORDER BY event_date, type_order, event_id, row_id ROWS UNBOUNDED PRECEDING) AS balance');

        return DB::query()->fromSub($running, 'ledger')->when($filters['from_date'] ?? null, fn ($q, $date) => $q->where('event_date', '>=', $date))->when($filters['to_date'] ?? null, fn ($q, $date) => $q->where('event_date', '<=', $date))->orderBy('event_date')->orderBy('type_order')->orderBy('event_id')->orderBy('row_id');
    }
}
