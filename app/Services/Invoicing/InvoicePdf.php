<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceProductLink;
use App\Services\Business\Modules;
use App\Services\Documents\Documents;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class InvoicePdf
{
    public function filename(Invoice $invoice): string
    {
        $number = preg_replace('/[\x00-\x1F\x7F\/:*?"<>|\\\\]/u', '-', $invoice->invoice_no);

        return 'Invoice-'.mb_substr($number, 0, 100).'.pdf';
    }

    public function render(Invoice $invoice)
    {
        $invoice->loadMissing('items', 'customer', 'vendor');
        $productUnits = app(Modules::class)->installed('products') ? InvoiceProductLink::where('invoice_id', $invoice->id)->pluck('unit', 'invoice_item_id')->all() : [];

        $indic = in_array(app()->getLocale(), ['gu', 'hi'], true) || preg_match('/[\x{0900}-\x{097F}\x{0A80}-\x{0AFF}]/u', $invoice->toJson());

        return app(Documents::class)->render($indic ? 'invoices.pdf-indic' : 'invoices.pdf', compact('invoice', 'productUnits'));
    }

    public function archive($query, string $downloadName)
    {
        $directory = storage_path('app/temp');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create PDF download directory.');
        }
        $path = $directory.'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Could not open PDF archive.');
            }
            $opened = true;
            $seen = [];
            foreach ($query->with(['items', 'customer', 'vendor'])->lazyById(50) as $invoice) {
                $name = str_replace('Invoice-', 'Invoice_', $this->filename($invoice));
                while (isset($seen[mb_strtolower($name)])) {
                    $name = mb_substr($name, 0, -4).'-'.$invoice->id.'.pdf';
                }
                $seen[mb_strtolower($name)] = true;
                if (! $zip->addFromString($name, $this->render($invoice)->output())) {
                    throw new RuntimeException('Could not add invoice PDF to archive.');
                }
            }
            $closed = $zip->close();
            $opened = false;
            if (! $closed) {
                throw new RuntimeException('Could not finish PDF archive.');
            }

            return response()->download($path, $downloadName)->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }
}
