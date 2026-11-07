<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentFilterRequest;
use App\Models\Invoice;
use App\Models\InvoiceReceivable;
use App\Models\User;
use App\Services\Payments\Money;
use App\Services\Payments\PaymentQueries;
use App\Services\Payments\PaymentWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableController extends Controller
{
    public function __construct(private PaymentQueries $queries, private PaymentWriter $writer) {}

    public function index(PaymentFilterRequest $request)
    {
        $q = DB::query()->fromSub($this->queries->balances($request->user()->id, $request->validated('customer_id')), 'b');
        if ($text = $request->validated('q')) {
            $q->where(fn ($q) => $q->where('invoice_no', 'like', '%'.$text.'%')->orWhere('customer_name', 'like', '%'.$text.'%'));
        }
        $mode = $request->validated('balance') ?? 'outstanding';
        if ($mode === 'review') {
            $q->whereNull('confirmed_at');
        } else {
            $q->whereNotNull('confirmed_at')->where('outstanding_cents', '>', 0);
            if ($mode === 'overdue') {
                $q->where('due_date', '<', now(config('bizpilot.timezone'))->toDateString());
            }
        }
        $invoices = $q->orderBy('invoice_date')->orderBy('id')->paginate(20)->withQueryString();
        $summary = $this->queries->confirmedTotals($request->user()->id, $request->validated('customer_id'));

        return view('receivables.index', compact('invoices', 'summary', 'mode'));
    }

    public function review(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate(['action' => 'required|in:begin,confirm', 'original_version' => 'required|string|size:64', 'due_date' => 'nullable|date_format:Y-m-d', 'review_note' => 'required|string|min:5|max:1000', 'acknowledge' => 'accepted']);
        $this->writer->review($request->user(), $invoice, $data);

        return redirect()->route('invoices.show', $invoice)->with('success', $data['action'] === 'begin' ? 'Review started. Record actual prior payments, then confirm the balance.' : 'Payment review completed. Balance now included in receivables.');
    }

    public function dueDate(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate(['due_date' => 'nullable|date_format:Y-m-d', 'expected_due_date' => 'nullable|date_format:Y-m-d']);
        DB::transaction(function () use ($request, $invoice, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $saved = Invoice::whereKey($invoice->id)->where('vendor_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $row = InvoiceReceivable::where('invoice_id', $saved->id)->lockForUpdate()->firstOrFail();
            if (($row->due_date ?? '') !== ($data['expected_due_date'] ?? '')) {
                throw ValidationException::withMessages(['due_date' => 'Due date changed in another tab. Reload before saving.']);
            }
            if (! empty($data['due_date']) && $data['due_date'] < $saved->invoice_date) {
                throw ValidationException::withMessages(['due_date' => 'Due date cannot be before invoice date.']);
            }
            $row->update(['due_date' => $data['due_date'] ?? null]);
        }, 5);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Due date updated.');
    }

    public function ledger(PaymentFilterRequest $request, User $customer)
    {
        abort_unless($customer->role === 'customer' && (int) $customer->created_by === $request->user()->id, 403);
        $entries = $this->queries->ledger($request->user()->id, $customer->id, $request->validated())->paginate(30)->withQueryString();
        $summary = $this->queries->confirmedTotals($request->user()->id, $customer->id);

        return view('receivables.ledger', compact('customer', 'entries', 'summary'));
    }

    public function export(PaymentFilterRequest $request, User $customer)
    {
        abort_unless($customer->role === 'customer' && (int) $customer->created_by === $request->user()->id, 403);
        $query = $this->queries->ledger($request->user()->id, $customer->id, $request->validated());

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Entry', 'Reference', 'Debit INR', 'Credit INR', 'Running balance INR']);
            foreach ($query->cursor() as $r) {
                fputcsv($out, [$r->event_date, $r->kind, PaymentController::csv($r->reference), Money::decimal((int) $r->debit), Money::decimal((int) $r->credit), Money::decimal((int) $r->balance)]);
            }fclose($out);
        }, 'customer-ledger.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
