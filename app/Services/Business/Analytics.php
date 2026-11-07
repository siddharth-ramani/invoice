<?php

namespace App\Services\Business;

use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Payments\PaymentQueries;
use Illuminate\Support\Facades\DB;

class Analytics
{
    public function summary(int $vendor, array $dates): array
    {
        $invoices = Invoice::where('vendor_id', $vendor)->whereBetween('invoice_date', [$dates['from_date'], $dates['to_date']]);
        $sales = (clone $invoices)->selectRaw('COUNT(*) AS count, COALESCE(SUM(ROUND(grand_total*100,0)),0) AS gross, COALESCE(SUM(ROUND((taxable_amount-discount)*100,0)),0) AS pretax')->first();
        $expenseQuery = Expense::where('expenses.vendor_id', $vendor)->whereNull('voided_at')->whereBetween('expense_date', [$dates['from_date'], $dates['to_date']]);
        $expenses = (int) (clone $expenseQuery)->sum(DB::raw('ROUND(amount*100,0)'));
        $collections = app(PaymentQueries::class)->collections($vendor, $dates);
        $receivables = app(PaymentQueries::class)->confirmedTotals($vendor);
        $lines = DB::table('invoice_items as item')->join('invoices as i', 'i.id', '=', 'item.invoice_id')->leftJoin('invoice_product_links as link', 'link.invoice_item_id', '=', 'item.id')->where('i.vendor_id', $vendor)->whereNull('i.deleted_at')->whereBetween('i.invoice_date', [$dates['from_date'], $dates['to_date']]);
        $cost = (clone $lines)->selectRaw('COUNT(*) AS line_count, COALESCE(SUM(CASE WHEN link.cost_rate IS NULL THEN 1 ELSE 0 END),0) AS missing, COALESCE(SUM(ROUND(link.quantity*link.cost_rate*100,0)),0) AS cost')->first();
        $profit = ($cost->missing > 0 || (clone $invoices)->whereDoesntHave('items')->exists()) ? null : (int) $sales->pretax - (int) $cost->cost - $expenses;
        $months = (clone $invoices)->selectRaw('SUBSTR(invoice_date,1,7) AS month, SUM(grand_total) AS sales')->groupByRaw('SUBSTR(invoice_date,1,7)')->orderBy('month')->get();
        $categories = (clone $expenseQuery)->join('expense_categories as c', 'c.id', '=', 'expenses.expense_category_id')->selectRaw('c.name, SUM(expenses.amount) AS amount')->groupBy('c.id', 'c.name')->orderByDesc('amount')->limit(20)->get();
        $customers = (clone $invoices)->join('users as c', 'c.id', '=', 'invoices.customer_id')->where('c.created_by', $vendor)->selectRaw('c.id, c.name, COUNT(*) AS count, SUM(invoices.grand_total) AS amount')->groupBy('c.id', 'c.name')->orderByDesc('amount')->limit(10)->get();
        $products = (clone $lines)->join('products as p', 'p.id', '=', 'link.product_id')->selectRaw('p.id,p.name,link.unit,SUM(link.quantity) AS quantity,SUM(item.line_total) AS sales')->groupBy('p.id', 'p.name', 'link.unit')->orderByDesc('sales')->limit(20)->get();

        return compact('sales', 'expenses', 'collections', 'receivables', 'cost', 'profit', 'months', 'categories', 'customers', 'products');
    }
}
