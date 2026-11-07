<?php

namespace App\Services\Invoicing;

use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    // All intermediate monetary values use integer 1/100,000,000 rupees.
    // Bounds below keep all arithmetic inside signed 64-bit integers.
    private const SCALE = 100000000;

    private const LINE_MAX = 9999999999000000;

    private const INVOICE_MAX = 999999999999000000;

    public static function units(string|int|float $value): int
    {
        $text = (string) $value;
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) {
            throw ValidationException::withMessages(['items' => 'Amounts, quantities and percentages must use at most two decimal places.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $text, 2), 2, '');
        if (strlen(ltrim($whole, '0')) > 10) {
            throw ValidationException::withMessages(['items' => 'A value exceeds the supported financial range.']);
        }

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $scaled): string
    {
        $sign = $scaled < 0 ? '-' : '';
        $cents = intdiv(abs($scaled) + 500000, 1000000);
        if ($cents === 0) {
            $sign = '';
        }

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function calculate(array $data): array
    {
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException('Invoicing requires a 64-bit PHP runtime.');
        }
        if (empty($data['items']) || ! is_array($data['items']) || count($data['items']) > 200) {
            throw ValidationException::withMessages(['items' => 'Provide between 1 and 200 invoice items.']);
        }
        $taxable = $cgstTotal = $sgstTotal = 0;
        $lines = [];
        foreach (array_values($data['items']) as $index => $item) {
            $qty = self::units($item['qty']);
            $rate = self::units($item['rate']);
            $cgstPercent = self::units($item['cgst_percent']);
            $sgstPercent = self::units($item['sgst_percent']);
            if ($qty < 1 || $qty > 10000000 || $rate > 1000000000 || $cgstPercent > 2800 || $sgstPercent > 2800) {
                throw ValidationException::withMessages(['items' => 'An item exceeds the supported quantity, rate or tax range.']);
            }
            $base4 = $qty * $rate;
            if ($base4 > intdiv(self::LINE_MAX, 10000)) {
                throw ValidationException::withMessages(["items.$index.rate" => 'This line exceeds the current database amount limit. Reduce the quantity or rate.']);
            }
            $base = $base4 * 10000;
            $cgst = $base4 * $cgstPercent;
            $sgst = $base4 * $sgstPercent;
            $lineTotal = $base + $cgst + $sgst;
            if ($lineTotal > self::LINE_MAX) {
                throw ValidationException::withMessages(["items.$index.rate" => 'This line including GST exceeds the current database amount limit.']);
            }
            $taxable += $base;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            if ($taxable + $cgstTotal + $sgstTotal > self::INVOICE_MAX) {
                throw ValidationException::withMessages(['items' => 'The invoice exceeds the current database amount limit.']);
            }
            $lines[] = [
                'sr_no' => $index + 1, 'item_name' => $item['item_name'], 'hsn_sac' => $item['hsn_sac'] ?? null,
                'qty' => self::decimal($qty * 1000000), 'rate' => self::decimal($rate * 1000000),
                'taxable_value' => self::decimal($base), 'cgst_percent' => self::decimal($cgstPercent * 1000000),
                'sgst_percent' => self::decimal($sgstPercent * 1000000), 'cgst_amount' => self::decimal($cgst),
                'sgst_amount' => self::decimal($sgst), 'line_total' => self::decimal($lineTotal),
            ];
        }
        $discountPercent = self::units($data['discount_percent'] ?? 0);
        if ($discountPercent > 10000) {
            throw ValidationException::withMessages(['discount_percent' => 'Discount must be between 0 and 100 percent.']);
        }
        $discount = intdiv($taxable, 10000) * $discountPercent;
        // The live legacy column is DECIMAL(10,0). Never silently truncate paise.
        if ($discount % self::SCALE !== 0) {
            throw ValidationException::withMessages(['discount_percent' => 'The current database stores discounts in whole rupees. Adjust the percentage so the discount is a whole rupee.']);
        }
        $before = $taxable - $discount + $cgstTotal + $sgstTotal;
        $grand = intdiv($before + 50000000, self::SCALE) * self::SCALE;
        if ($grand > self::INVOICE_MAX) {
            throw ValidationException::withMessages(['items' => 'Rounded invoice total exceeds the current database limit.']);
        }

        return ['totals' => [
            'taxable_amount' => self::decimal($taxable), 'discount_percent' => self::decimal($discountPercent * 1000000),
            'discount' => self::decimal($discount), 'cgst_total' => self::decimal($cgstTotal), 'sgst_total' => self::decimal($sgstTotal),
            'total_tax' => self::decimal($cgstTotal + $sgstTotal), 'total_before_round' => self::decimal($before),
            'grand_total' => self::decimal($grand), 'round_off' => self::decimal($grand - $before),
        ], 'items' => $lines];
    }
}
