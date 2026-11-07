<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceListRequest;
use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Invoice;
use App\Models\InvoiceProductLink;
use App\Models\InvoiceReceivable;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\Business\Catalog;
use App\Services\Business\Modules;
use App\Services\Invoicing\InvoiceFilters;
use App\Services\Invoicing\InvoiceNumber;
use App\Services\Invoicing\InvoicePdf;
use App\Services\Invoicing\InvoiceVersion;
use App\Services\Invoicing\InvoiceWriter;
use App\Services\Invoicing\SubscriptionAccess;
use App\Services\Payments\PaymentModule;
use App\Services\Payments\PaymentQueries;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceWriter $writer, private InvoiceNumber $numbers, private InvoiceVersion $versions, private SubscriptionAccess $subscriptions, private InvoicePdf $pdfs, private InvoiceFilters $filters) {}

    public function index(InvoiceListRequest $request)
    {
        $query = Invoice::where('vendor_id', $request->user()->id)->with(['customer', 'items']);
        $this->filters->apply($query, $request->validated(), $request->user()->id);
        $invoices = $this->filters->sort($query, $request->validated('sort'))->paginate(20)->withQueryString();
        $invoiceVersions = [];
        foreach ($invoices as $invoice) {
            $invoiceVersions[$invoice->id] = $this->versions->token($invoice);
        }

        return view('invoices.index', compact('invoices', 'invoiceVersions'));
    }

    public function create()
    {
        $this->authorize('create', Invoice::class);
        try {
            $this->subscriptions->check(auth()->user(), true);
        } catch (ValidationException $e) {
            return redirect()->route('subscription')->with('error', collect($e->errors())->flatten()->first());
        }
        $nextInvoiceNo = $this->numbers->next(auth()->user());
        $customers = User::where('role', 'customer')->where('created_by', auth()->id())->orderBy('name')->get();

        return view('invoices.create', compact('customers', 'nextInvoiceNo'));
    }

    public function store(InvoiceRequest $request)
    {
        $result = $this->writer->create($request->user(), $request->validated());

        return redirect()->route('invoices.show', $result['invoice'])->with('success', $result['replayed'] ? 'This invoice was already saved. Opened the existing invoice.' : 'Invoice created successfully');
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load('items', 'customer', 'vendor');
        $paymentTracking = app(PaymentModule::class)->ready();
        $receivable = $paymentTracking ? InvoiceReceivable::where('invoice_id', $invoice->id)->first() : null;
        $paymentBalance = $paymentTracking ? app(PaymentQueries::class)->balances(auth()->id())->where('i.id', $invoice->id)->first() : null;
        $paymentHistory = $paymentTracking ? PaymentAllocation::where('invoice_id', $invoice->id)->whereHas('payment', fn ($q) => $q->where('vendor_id', auth()->id()))->with(['payment.reversal'])->orderByDesc('id')->paginate(10) : null;
        $originalVersion = $this->versions->token($invoice);

        $productUnits = app(Modules::class)->installed('products') ? InvoiceProductLink::where('invoice_id', $invoice->id)->pluck('unit', 'invoice_item_id')->all() : [];

        return view('invoices.show', compact('invoice', 'paymentTracking', 'receivable', 'paymentBalance', 'paymentHistory', 'originalVersion', 'productUnits'));
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        try {
            $this->subscriptions->check(auth()->user(), false);
        } catch (ValidationException $e) {
            return redirect()->route('vendor.subscription')->with('error', collect($e->errors())->flatten()->first());
        }
        $invoice->load('items');
        $customers = User::where('role', 'customer')->where('created_by', auth()->id())->orderBy('name')->get();
        $originalVersion = $this->versions->token($invoice);
        $invoiceProducts = app(Catalog::class)->links($invoice);

        return view('invoices.edit', compact('invoice', 'customers', 'originalVersion', 'invoiceProducts'));
    }

    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        $saved = $this->writer->update($request->user(), $invoice, $request->validated());

        return redirect()->route('invoices.show', $saved)->with('success', 'Invoice updated successfully');
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        $this->authorize('delete', $invoice);
        $data = $request->validate(['original_version' => 'required|string|size:64']);
        $this->writer->delete($request->user(), $invoice, $data['original_version']);

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully');
    }

    public function print(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return $this->pdfs->render($invoice)->stream($this->pdfs->filename($invoice));
    }

    public function download(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return $this->pdfs->render($invoice)->download($this->pdfs->filename($invoice));
    }

    public function pdf(Invoice $invoice)
    {
        return $this->download($invoice);
    }

    public function bulkDownload(ReportFilterRequest $request)
    {
        $request->validate(['from_date' => 'required|date_format:Y-m-d', 'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date']);
        $query = Invoice::where('vendor_id', $request->user()->id)->whereBetween('invoice_date', [$request->from_date, $request->to_date]);
        if (! $query->exists()) {
            return back()->with('error', 'No invoices found for selected dates');
        }

        return $this->pdfs->archive($query, 'Invoices_'.Carbon::parse($request->from_date)->format('M_Y').'.zip');
    }

    public function bulkDownloadByDate(ReportFilterRequest $request)
    {
        return $this->bulkDownload($request);
    }
}
