<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class InvoiceNumber
{
    public function next(User $vendor): string
    {
        $prefix = $vendor->invoice_prefix ?? 'GT';
        if (mb_strlen($prefix) > 40 || preg_match('/[\x00-\x1F\x7F\\\\]/u', $prefix)) {
            throw ValidationException::withMessages(['invoice_no' => 'Update the invoice prefix in business settings before creating invoices.']);
        }
        $pattern = '/^'.preg_quote($prefix, '/').'\/(\d+)$/iuD';
        $max = '0';
        $like = strtr($prefix, ['!' => '!!', '%' => '!%', '_' => '!_']).'/%';
        foreach (Invoice::withTrashed()->where('vendor_id', $vendor->id)->whereRaw("invoice_no LIKE ? ESCAPE '!'", [$like])->select('invoice_no')->cursor() as $row) {
            if (! preg_match($pattern, $row->invoice_no, $match)) {
                continue;
            }
            $n = ltrim($match[1], '0') ?: '0';
            if (strlen($n) > strlen($max) || (strlen($n) === strlen($max) && strcmp($n, $max) > 0)) {
                $max = $n;
            }
        }
        $digits = str_split($max);
        $carry = 1;
        for ($i = count($digits) - 1; $i >= 0 && $carry; $i--) {
            $d = (int) $digits[$i] + 1;
            $digits[$i] = (string) ($d % 10);
            $carry = $d > 9 ? 1 : 0;
        }
        $next = ($carry ? '1' : '').implode('', $digits);
        $number = $prefix.'/'.str_pad($next, 3, '0', STR_PAD_LEFT);
        if (mb_strlen($number) > 50) {
            throw ValidationException::withMessages(['invoice_no' => 'Invoice sequence exceeds the supported length. Review the prefix in settings.']);
        }

        return $number;
    }
}
