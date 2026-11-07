<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceProductLink;
use App\Services\Business\Modules;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceArchive
{
    public function preserve(Invoice $invoice, string $action): void
    {
        $invoice->loadMissing('items');
        $request = app('request');
        $snapshot = ['kind' => 'pre-write-snapshot', 'action' => $action, 'captured_at' => now()->toIso8601String(),
            'vendor_id' => $invoice->vendor_id, 'originating_admin_id' => $request->hasSession() ? $request->session()->get('bizpilot.impersonation.admin_id') : null,
            'invoice' => $invoice->getAttributes(), 'items' => $invoice->items->map->getAttributes()->all()];
        $path = 'private/invoice-revisions/'.$invoice->vendor_id.'/'.$invoice->id.'/'.Str::uuid().'.json';
        if (app(Modules::class)->ready('products')) {
            $snapshot['catalog_links'] = InvoiceProductLink::where('invoice_id', $invoice->id)->get()->map->getAttributes()->all();
        }
        try {
            if (! Storage::disk('local')->put($path, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)), ['visibility' => 'private'])) {
                throw new \RuntimeException('Private snapshot write failed.');
            }
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['archive' => 'Could not preserve the earlier saved invoice. No changes were made. Please retry or contact your administrator.']);
        }
    }
}
