<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentFilterRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\Documents;
use App\Services\Payments\PaymentQueries;
use App\Services\Payments\PaymentWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private PaymentWriter $writer, private PaymentQueries $queries) {}

    public function index(PaymentFilterRequest $request)
    {
        $this->authorize('viewAny', Payment::class);
        $payments = $this->queries->payments($request->user()->id, $request->validated())->with(['customer', 'reversal'])->orderByDesc('payment_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $summary = $this->queries->collections($request->user()->id, $request->validated());
        $reversals = $this->queries->reversals($request->user()->id, $request->validated())->with('reversal')->orderByDesc('id')->paginate(20, ['*'], 'reversals_page')->withQueryString();

        return view('payments.index', compact('payments', 'summary', 'reversals'));
    }

    public function create(PaymentFilterRequest $request)
    {
        $this->authorize('create', Payment::class);
        $customers = User::where('created_by', $request->user()->id)->where('role', 'customer')->orderBy('name')->get();
        $customerId = (int) old('customer_id', $request->validated('customer_id'));
        $invoices = $customerId ? $this->queries->balances($request->user()->id, $customerId)->whereNotNull('r.id')->when($request->validated('q'), fn ($q, $text) => $q->where('i.invoice_no', 'like', '%'.$text.'%'))->whereRaw('ROUND(i.grand_total * 100,0) > COALESCE(allocated.paid_cents,0)')->orderBy('i.invoice_date')->orderBy('i.id')->limit(100)->get() : collect();
        $idempotencyKey = Str::uuid()->toString();

        return view('payments.create', compact('customers', 'customerId', 'invoices', 'idempotencyKey'));
    }

    public function store(PaymentRequest $request)
    {
        $result = $this->writer->create($request->user(), $request->validated());

        return redirect()->route('payments.show', $result['payment'])->with('success', $result['replayed'] ? 'This payment was already saved. Opened the existing receipt.' : 'Payment recorded successfully.');
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);
        $payment->load('allocations', 'reversal');

        return view('payments.show', compact('payment'));
    }

    public function receipt(Payment $payment)
    {
        $this->authorize('view', $payment);
        $payment->load('allocations', 'reversal');

        return app(Documents::class)->render('payments.receipt', compact('payment'))->download('Receipt-'.str_replace('/', '-', $payment->receipt_no).'.pdf');
    }

    public function reverse(Request $request, Payment $payment)
    {
        $this->authorize('reverse', $payment);
        $data = $request->validate(['reversal_date' => 'required|date_format:Y-m-d|before_or_equal:'.now(config('bizpilot.timezone'))->toDateString(), 'reason' => 'required|string|min:5|max:1000', 'confirm_reversal' => 'accepted']);
        $this->writer->reverse($request->user(), $payment, $data);

        return redirect()->route('payments.show', $payment)->with('success', 'Payment reversed. Original receipt and allocations retained.');
    }

    public function export(PaymentFilterRequest $request)
    {
        $filters = $request->validated();
        $issued = $this->queries->payments($request->user()->id, $filters)->with('reversal')->orderBy('id');
        $reversed = $this->queries->payments($request->user()->id, $filters, false)->whereHas('reversal', function ($q) use ($filters) {
            if (! empty($filters['from_date'])) {
                $q->where('reversal_date', '>=', $filters['from_date']);
            }if (! empty($filters['to_date'])) {
                $q->where('reversal_date', '<=', $filters['to_date']);
            }
        })->with('reversal')->orderBy('id');

        return response()->streamDownload(function () use ($issued, $reversed) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'Date', 'Receipt', 'Customer', 'Method', 'Reference', 'Received INR', 'Reversed INR']);
            foreach ($issued->lazyById(100) as $p) {
                fputcsv($out, ['Payment', $p->payment_date, self::csv($p->receipt_no), self::csv($p->party_snapshot['customer']['name']), $p->method, self::csv($p->reference), $p->amount, '0.00']);
            }foreach ($reversed->lazyById(100) as $p) {
                fputcsv($out, ['Reversal', $p->reversal->reversal_date, self::csv($p->receipt_no), self::csv($p->party_snapshot['customer']['name']), $p->method, self::csv($p->reference), '0.00', $p->amount]);
            }fclose($out);
        }, 'collections.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function csv(?string $value): string
    {
        return preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value ?? '') ? "'".$value : ($value ?? '');
    }
}
