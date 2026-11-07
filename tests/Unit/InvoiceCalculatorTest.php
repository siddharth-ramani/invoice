<?php

namespace Tests\Unit;

use App\Services\Invoicing\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    public function test_legacy_formula_with_decimal_quantities_mixed_gst_discount_and_rounding(): void
    {
        $c = new InvoiceCalculator;
        foreach ([
            ['1', '100', '9', '9', '0', '100.00', '18.00', '118.00', '0.00'],
            ['1', '100', '9', '9', '10', '100.00', '18.00', '108.00', '0.00'],
            ['2.50', '10.20', '2.50', '2.50', '0', '25.50', '1.28', '27.00', '0.23'],
            ['1', '0.50', '0', '0', '0', '0.50', '0.00', '1.00', '0.50'],
            ['1', '0.49', '0', '0', '0', '0.49', '0.00', '0.00', '-0.49'],
            ['1', '100', '14', '14', '100', '100.00', '28.00', '28.00', '0.00'],
        ] as [$qty,$rate,$cgst,$sgst,$discount,$base,$tax,$grand,$round]) {
            $result = $c->calculate(['discount_percent' => $discount, 'items' => [['item_name' => 'Item', 'qty' => $qty, 'rate' => $rate, 'cgst_percent' => $cgst, 'sgst_percent' => $sgst]]]);
            $this->assertSame($base, $result['totals']['taxable_amount']);
            $this->assertSame($tax, $result['totals']['total_tax']);
            $this->assertSame($grand, $result['totals']['grand_total']);
            $this->assertSame($round, $result['totals']['round_off']);
        }
        $r = $c->calculate(['items' => [['item_name' => 'A', 'qty' => '1', 'rate' => '10', 'cgst_percent' => '9', 'sgst_percent' => '9'], ['item_name' => 'B', 'qty' => '1', 'rate' => '10', 'cgst_percent' => '2.5', 'sgst_percent' => '2.5']]]);
        $this->assertSame('2.30', $r['totals']['total_tax']);
        $this->assertSame('22.00', $r['totals']['grand_total']);
    }
}
