<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Services\Business\Analytics;
use App\Services\Payments\Money;

class AnalyticsController extends Controller
{
    public function index(DashboardRequest $request)
    {
        abort_unless($request->user()->role === 'vendor', 403);
        $dates = $request->validated();
        $data = app(Analytics::class)->summary($request->user()->id, $dates);

        return view('analytics.index', array_merge($data, ['dates' => $dates]));
    }

    public function export(DashboardRequest $request)
    {
        abort_unless($request->user()->role === 'vendor', 403);
        $dates = $request->validated();
        $data = app(Analytics::class)->summary($request->user()->id, $dates);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric', 'INR or count', 'Date basis']);
            foreach (['Invoice sales' => [$data['sales']->gross, 'Invoice dates'], 'Received' => [$data['collections']['gross'], 'Payment dates'], 'Reversed' => [$data['collections']['reversed'], 'Reversal dates'], 'Net collections' => [$data['collections']['net'], 'Payment/reversal dates'], 'Expenses' => [$data['expenses'], 'Expense dates'], 'Reviewed outstanding' => [$data['receivables']['outstanding'], 'Current, all dates'], 'Reviewed overdue' => [$data['receivables']['overdue'], 'Current, explicit due dates']] as $label => [$amount,$basis]) {
                fputcsv($out, [$label, Money::decimal((int) $amount), $basis]);
            }fputcsv($out, ['Estimated operating surplus', $data['profit'] === null ? 'Unavailable: missing costs' : Money::decimal($data['profit']), 'Selected invoice/expense dates; pre-tax sales less saved catalog costs and expenses']);
            fputcsv($out, ['Invoices awaiting payment review', $data['receivables']['pending'], 'Current, excluded from outstanding']);
            fclose($out);
        }, 'business-analytics.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
