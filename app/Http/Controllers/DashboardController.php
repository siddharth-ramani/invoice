<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Payments\PaymentModule;
use App\Services\Payments\PaymentQueries;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request)
    {
        $user = $request->user();
        $dates = $request->validated();
        $from = CarbonImmutable::parse($dates['from_date'], config('bizpilot.timezone'));
        $to = CarbonImmutable::parse($dates['to_date'], config('bizpilot.timezone'));

        $isAdmin = $user->role === 'admin';
        $query = Invoice::query()
            ->when(! $isAdmin, fn ($query) => $query->where('vendor_id', $user->id))
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()]);

        // Read stored snapshots only. Never recalculate historical invoices.
        $summary = (clone $query)->selectRaw(
            'COUNT(*) AS invoice_count, COALESCE(SUM(grand_total), 0) AS sales, COALESCE(SUM(total_tax), 0) AS tax, COUNT(DISTINCT customer_id) AS customer_count'
        )->first();

        // ISO date substring works on both the existing MariaDB and isolated SQLite tests.
        $monthlyTotals = (clone $query)
            ->selectRaw('SUBSTR(invoice_date, 1, 7) AS month, SUM(grand_total) AS sales')
            ->groupByRaw('SUBSTR(invoice_date, 1, 7)')->orderBy('month')->pluck('sales', 'month');
        $months = [];
        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to->startOfMonth()); $month = $month->addMonth()) {
            $months[] = ['label' => $month->format('M Y'), 'sales' => (float) ($monthlyTotals[$month->format('Y-m')] ?? 0)];
        }

        $customerScope = function ($query) use ($isAdmin, $user) {
            $query->select('id', 'name')->when(! $isAdmin, fn ($query) => $query->where('created_by', $user->id)->where('role', 'customer'));
        };
        $recentInvoices = (clone $query)->with(['customer' => $customerScope])
            ->orderByDesc('invoice_date')->orderByDesc('id')->limit(6)->get();
        $topCustomers = (clone $query)->select('customer_id')
            ->selectRaw('SUM(grand_total) AS sales, COUNT(*) AS invoice_count')
            ->groupBy('customer_id')->orderByDesc('sales')->orderBy('customer_id')->limit(5)
            ->with(['customer' => $customerScope])->get();

        $accounts = $isAdmin ? User::query()->selectRaw(
            "SUM(CASE WHEN role = 'vendor' THEN 1 ELSE 0 END) AS vendors, SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) AS customers"
        )->first() : null;

        $paymentTracking = ! $isAdmin && app(PaymentModule::class)->ready();
        $collections = $receivables = null;
        $recentPayments = collect();
        if ($paymentTracking) {
            $payments = app(PaymentQueries::class);
            $period = ['from_date' => $from->toDateString(), 'to_date' => $to->toDateString()];
            $collections = $payments->collections($user->id, $period);
            $receivables = $payments->confirmedTotals($user->id);
            $recentPayments = $payments->payments($user->id, $period)->with('reversal')->orderByDesc('payment_date')->orderByDesc('id')->limit(5)->get();
        }

        return view('dashboard', compact('summary', 'months', 'recentInvoices', 'topCustomers', 'from', 'to', 'isAdmin', 'accounts', 'paymentTracking', 'collections', 'receivables', 'recentPayments'));
    }
}
