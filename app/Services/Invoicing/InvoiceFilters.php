<?php

namespace App\Services\Invoicing;

use Illuminate\Database\Eloquent\Builder;

class InvoiceFilters
{
    public function apply(Builder $query, array $filters, int $vendorId): Builder
    {
        if ($q = $filters['q'] ?? null) {
            $query->where(function ($query) use ($q, $vendorId) {
                $query->where('invoice_no', 'like', '%'.$q.'%')->orWhereHas('customer', fn ($customer) => $customer->where('created_by', $vendorId)->where('name', 'like', '%'.$q.'%'));
            });
        }
        if ($date = $filters['from_date'] ?? null) {
            $query->whereDate('invoice_date', '>=', $date);
        }
        if ($date = $filters['to_date'] ?? null) {
            $query->whereDate('invoice_date', '<=', $date);
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function sort(Builder $query, ?string $sort): Builder
    {
        $sorts = ['newest' => ['id', 'desc'], 'oldest' => ['id', 'asc'], 'number' => ['invoice_no', 'asc'], 'amount_desc' => ['grand_total', 'desc'], 'amount_asc' => ['grand_total', 'asc']];
        [$column,$direction] = $sorts[$sort ?? 'newest'];

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
