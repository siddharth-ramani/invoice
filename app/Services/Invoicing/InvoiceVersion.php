<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Services\Business\Catalog;

class InvoiceVersion
{
    public function token(Invoice $invoice): string
    {
        $invoice->loadMissing('items');
        $attributes = $invoice->getAttributes();
        ksort($attributes);
        $items = $invoice->items->sortBy('id')->map(function ($item) {
            $data = $item->getAttributes();
            ksort($data);

            return $data;
        })->values()->all();

        $links = app(Catalog::class)->links($invoice);

        return hash_hmac('sha256', json_encode([$attributes, $items, $links], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function canonical(array $data): array
    {
        return [
            'customer_id' => (int) $data['customer_id'], 'invoice_date' => (string) $data['invoice_date'],
            'notes' => (string) ($data['notes'] ?? ''), 'discount_percent' => InvoiceCalculator::units($data['discount_percent'] ?? 0),
            'items' => array_map(fn ($item) => [
                'item_name' => (string) $item['item_name'], 'hsn_sac' => (string) ($item['hsn_sac'] ?? ''),
                'product_id' => ! empty($item['product_id']) ? (int) $item['product_id'] : null,
                'qty' => InvoiceCalculator::units($item['qty']), 'rate' => InvoiceCalculator::units($item['rate']),
                'cgst_percent' => InvoiceCalculator::units($item['cgst_percent']), 'sgst_percent' => InvoiceCalculator::units($item['sgst_percent']),
            ], array_values($data['items'])),
        ];
    }

    public function stored(Invoice $invoice): array
    {
        $invoice->loadMissing('items');

        $links = app(Catalog::class)->links($invoice);
        $items = $invoice->items->sortBy('sr_no')->values()->map(fn ($item) => array_merge($item->toArray(), ['product_id' => $links[$item->id] ?? null]))->all();

        return $this->canonical(array_merge($invoice->getAttributes(), ['items' => $items]));
    }

    public function financial(array $canonical): array
    {
        return [$canonical['discount_percent'], array_map(fn ($item) => array_intersect_key($item, array_flip(['qty', 'rate', 'cgst_percent', 'sgst_percent', 'product_id'])), $canonical['items'])];
    }
}
