<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ListRequest;
use App\Http\Requests\Business\QuotationRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Business\QuotationWriter;
use App\Services\Documents\Documents;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(private QuotationWriter $writer) {}

    public function index(ListRequest $request)
    {
        $quotations = Quotation::where('vendor_id', $request->user()->id)->when($request->validated('q'), fn ($q, $text) => $q->where('quotation_no', 'like', '%'.$text.'%'))->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))->orderByDesc('id')->paginate(20)->withQueryString();

        return view('quotations.index', compact('quotations'));
    }

    public function create()
    {
        $customers = User::where('created_by', auth()->id())->where('role', 'customer')->orderBy('name')->get();

        return view('quotations.form', compact('customers'));
    }

    public function store(QuotationRequest $request)
    {
        $q = $this->writer->save($request->user(), $request->validated());

        return redirect()->route('quotations.show', $q)->with('success', 'Quotation created.');
    }

    public function show(Quotation $quotation)
    {
        $this->authorize('view', $quotation);
        $quotation->load('items');
        $events = $quotation->events()->orderByDesc('id')->paginate(10);

        return view('quotations.show', compact('quotation', 'events'));
    }

    public function edit(Quotation $quotation)
    {
        $this->authorize('update', $quotation);
        abort_unless($quotation->status === 'draft', 403);
        $quotation->load('items');
        $customers = User::where('created_by', auth()->id())->where('role', 'customer')->orderBy('name')->get();

        return view('quotations.form', compact('quotation', 'customers'));
    }

    public function update(QuotationRequest $request, Quotation $quotation)
    {
        $saved = $this->writer->save($request->user(), $request->validated(), $quotation);

        return redirect()->route('quotations.show', $saved)->with('success', 'Quotation updated.');
    }

    public function status(Request $request, Quotation $quotation)
    {
        $this->authorize('update', $quotation);
        $data = $request->validate(['status' => 'required|in:sent,accepted,rejected,expired', 'revision' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:1000']);
        $this->writer->status($request->user(), $quotation, $data['status'], (int) $data['revision'], $data['reason']);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation status updated.');
    }

    public function duplicate(Quotation $quotation)
    {
        $this->authorize('view', $quotation);
        $copy = $this->writer->duplicate(auth()->user(), $quotation);

        return redirect()->route('quotations.edit', $copy)->with('success', 'A new draft quotation was created.');
    }

    public function convert(Request $request, Quotation $quotation)
    {
        $this->authorize('update', $quotation);
        $data = $request->validate(['revision' => 'required|integer|min:1', 'confirm_conversion' => 'accepted']);
        $invoice = $this->writer->convert($request->user(), $quotation, (int) $data['revision']);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Quotation converted. Repeated requests open the same invoice.');
    }

    public function pdf(Quotation $quotation)
    {
        $this->authorize('view', $quotation);
        $quotation->load('items');

        return app(Documents::class)->render('quotations.pdf', compact('quotation'))->download('Quotation-'.str_replace('/', '-', $quotation->quotation_no).'.pdf');
    }
}
