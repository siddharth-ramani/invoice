<?php

namespace App\Services;

class NumberToWordsService
{
    public static function convert($amount)
    {
        $amount = round($amount, 2);

        $number = floor($amount);
        $decimal = round(($amount - $number) * 100);

        $words = self::numberToWords($number);

        if ($decimal > 0) {
            $words .= ' and '.self::numberToWords($decimal).' Paise';
        }

        return 'Rupees '.ucfirst($words).' Only';
    }

    private static function numberToWords($num)
    {
        $ones = [
            '', 'one', 'two', 'three', 'four', 'five', 'six',
            'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
            'thirteen', 'fourteen', 'fifteen', 'sixteen',
            'seventeen', 'eighteen', 'nineteen',
        ];

        $tens = [
            '', '', 'twenty', 'thirty', 'forty', 'fifty',
            'sixty', 'seventy', 'eighty', 'ninety',
        ];

        if ($num < 20) {
            return $ones[$num];
        }

        if ($num < 100) {
            return $tens[intdiv($num, 10)].' '.$ones[$num % 10];
        }

        if ($num < 1000) {
            return $ones[intdiv($num, 100)].' hundred '.self::numberToWords($num % 100);
        }

        if ($num < 100000) {
            return self::numberToWords(intdiv($num, 1000)).' thousand '.self::numberToWords($num % 1000);
        }

        if ($num < 10000000) {
            return self::numberToWords(intdiv($num, 100000)).' lakh '.self::numberToWords($num % 100000);
        }

        return self::numberToWords(intdiv($num, 10000000)).' crore '.self::numberToWords($num % 10000000);
    }
}
