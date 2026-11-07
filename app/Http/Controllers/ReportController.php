<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceListRequest;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceFilters;

class ReportController extends Controller
{
    public function __construct(private InvoiceFilters $filters) {}

    private function data(InvoiceListRequest $request): array
    {
        $query = $this->filters->apply(Invoice::where('vendor_id', $request->user()->id), $request->validated(), $request->user()->id);
        $summary = (clone $query)->selectRaw('COUNT(*) AS invoice_count, COALESCE(SUM(grand_total),0) AS sales, COALESCE(SUM(total_tax),0) AS tax, COALESCE(SUM(cgst_total),0) AS cgst, COALESCE(SUM(sgst_total),0) AS sgst')->first();
        $invoices = $this->filters->sort($query, $request->validated('sort'))->with('customer')->paginate(20)->withQueryString();

        return compact('summary', 'invoices');
    }

    public function sales(InvoiceListRequest $request)
    {
        $data = $this->data($request);
        $data['totalSales'] = $data['summary']->sales;
        $data['totalTax'] = $data['summary']->tax;

        return view('reports.sales', $data);
    }

    public function gst(InvoiceListRequest $request)
    {
        return view('reports.gst', $this->data($request));
    }
}
