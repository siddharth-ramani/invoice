<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
@page {
    size: A4;
    margin: 6mm;
}

body {
    font-family: DejaVu Sans;
    font-size: 10px;
    margin: 0;
    padding: 0;
}

/* Keep individual rows intact while allowing long item tables to paginate. */
table, tr, td, th {
    page-break-inside: avoid !important;
}

.items-table {
    page-break-inside: auto !important;
}

.items-table tbody tr {
    height: 18px; /* CONTROL ROW HEIGHT */
}


table {
    width: 100%;
    border-collapse: collapse;
}

td, th {
    border: 1px solid #000;
    padding: 2.5px;
    vertical-align: top;
}

.no-border td {
    border: none;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

/* HEADER */
.items-table thead th {
    border: 1px solid #000;
    padding: 4px;
    text-align: center;
}

/* BODY — ONLY VERTICAL LINES */
.items-table tbody td {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-top: none;
    border-bottom: none;
    padding: 4px;
    vertical-align: top;
}

/* FOOTER TOTAL LINE */
.items-table tfoot th {
    border-top: 1px solid #000;
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    padding: 5px;
}
/* OUTER BORDER */
.items-table {
    border: 1px solid #000;
}


/* Alignment helpers */
.center { text-align: center; }
.right { text-align: right; }
.bold { font-weight: bold; }
.small { font-size: 9px; }

.footer-wrap {
    width: 100%;
    border-top: 1px solid #000;
    font-size: 11px;
    page-break-inside: avoid !important;
}

.footer-left {
    width: 60%;
    vertical-align: top;
    padding: 6px;
}

.footer-right {
    width: 40%;
    vertical-align: top;
    border-left: 1px solid #000;
}

/* LEFT SIDE */
.words-title {
    font-weight: bold;
    border-bottom: 1px solid #000;
    padding-bottom: 3px;
}

.words-text {
    padding: 6px 0;
}

.terms {
    border-top: 1px solid #000;
    padding-top: 3px;
    font-size: 10px;
}

/* RIGHT SUMMARY */
.summary {
    width: 100%;
    border-collapse: collapse;
}

.summary td {
    border-bottom: 1px solid #000;
    padding: 4px;
}


.cert {
    font-size: 10px;
    padding-top: 6px;
}

.for {
    padding-top: 8px;
}

.sign {
    height: 45px;
    border-bottom: 1px solid #000;
}

.sign-text {
    font-size: 10px;
}

.center { text-align: center; }
.right { text-align: right; }

.certify {
    font-size: 10px;
    padding-top: 6px;
}

.sign-space {
    height: 50px;
    text-align: center;
}

.sign-text {
    font-size: 10px;
}

.b{
    font-weight: bold;
}

</style>
</head>
<body>

@php
use App\Services\NumberToWordsService;
$allUnits = $invoice->items->map(fn($line)=>($productUnits ?? [])[$line->id] ?? 'kg')->unique();
$commonUnit = $allUnits->count() === 1 ? $allUnits->first() : null;
@endphp
{{-- ================= HEADER ================= --}}
<table style="width:100%; font-family: DejaVu Sans; font-size:11px">
    <tr>
        <!-- RIGHT CONTACT -->
        <td style="width:60%; text-align:center; vertical-align:top; border:none">
            <div><strong>{{ __('|| Shree Ganeshay Namah : ||') }}</strong></div>
        </td>
    </tr>
</table>
<table style="width:100%; font-family: DejaVu Sans; font-size:11px">
    <tr>
        <!-- LEFT COMPANY -->
        <td style="width:80%; vertical-align:top; border:none">
            <div style="font-size:36PX; font-weight:bold;"> {{ $invoice->vendor->company_name }}</div>
            <div>
                @foreach(explode("\n", $invoice->vendor->address) as $addressline)
                    {{ trim($addressline) }}<br>
                @endforeach
            </div>
        </td>

        <!-- RIGHT CONTACT -->
        <td style="width:60%; text-align:right; vertical-align:top; border:none">
            <div><strong>{{ __('Phone :') }}</strong> {{ $invoice->vendor->mobile }}<br>{{ $invoice->vendor->mobile_2 }}</div>
        </td>
    </tr>
</table>
<!-- GST + TITLE BAR -->
<table style="width:100%; border-collapse:collapse; margin-top:8px;">
    <tr>
        <td style="width:30%; border:1px solid #000; padding:4px; border-right:none">
            <b>{{ __('GSTIN') }}</b> : {{ $invoice->vendor->gst_number }}
        </td>

        <td style="width:40%; border-top:1px solid #000; border-bottom:1px solid #000; text-align:center; font-size:14px; font-weight:bold; border-left:none; border-right:none">
            {{ __('TAX INVOICE') }}
        </td>

        <td style="width:30%; border:1px solid #000; text-align:right; padding:4px; font-size:10px; font-weight:bold; border-left:none">
            {{ __('ORIGINAL FOR RECIPIENT') }}
        </td>
    </tr>
</table>

<table class="invoice-header">
    {{-- Customer + Invoice row --}}
    <tr>
        {{-- Customer block --}}
        <td colspan="6">
            
            <span class="b">{{ __('Customer Detail') }}</span><br><br>

            <span class="b">{{ __('Name') }}</span> : {{ $invoice->customer->name }}<br>
            <span class="b">{{ __('Address') }}</span> : {{ $invoice->customer->address }}<br>
            <span class="b">{{ __('Phone') }}</span> : {{ $invoice->customer->mobile ?? '-' }}<br>
            <span class="b">{{ __('GSTIN') }}</span> : {{ $invoice->customer->gst_number ?? '-' }}<br>
        </td>

        {{-- Invoice block --}}
        <td colspan="6">
            <span class="b">{{ __('Invoice No.') }}</span> : {{ $invoice->invoice_no }}<br>

            <span class="b">{{ __('Invoice Date') }}</span> :
            {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-Y') }}<br>

            <!-- <span class="b">Challan No.</span> : {{ $invoice->invoice_no }}<br>

            <span class="b">Challan Date</span> :
            {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-Y') }} -->
        </td>
    </tr>
</table>

{{-- ================= ITEMS TABLE ================= --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:4%">{{ __('Sr.') }}<br>{{ __('No') }}</th>
            <th style="width:30%">{{ __('Name of Product / Service') }}</th>
            <th style="width:8%">HSN / SAC</th>
            <th style="width:8%">{{ __('Qty') }} <br> {{ $commonUnit ? mb_strtoupper($commonUnit) : __('Units') }}</th>
            <th style="width:10%">{{ __('Rate') }}<br>/ {{ $commonUnit === 'kg' ? __('Kgs.') : ($commonUnit ?? 'unit') }}</th>
            <th style="width:12%">{{ __('Taxable Value') }}</th>
            <th colspan="2" style="width:14%">CGST</th>
            <th colspan="2" style="width:14%">SGST</th>
            <th style="width:12%">{{ __('Total') }}</th>
        </tr>

        <tr>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th style="width:7%">%</th>
            <th style="width:7%">{{ __('Amount') }}</th>
            <th style="width:7%">%</th>
            <th style="width:7%">{{ __('Amount') }}</th>
            <th></th>
        </tr>
    </thead>

    <tbody>
        @foreach($invoice->items as $item)
        <tr>
            <td class="center">{{ $item->sr_no }}</td>
            <td>{{ $item->item_name }}</td>
            <td class="center">{{ $item->hsn_sac }}</td>
            <td class="right">{{ number_format($item->qty, 2) }}@if(!$commonUnit) {{ ($productUnits ?? [])[$item->id] ?? 'kg' }}@endif</td>
            <td class="right">{{ number_format($item->rate, 2) }}</td>
            <td class="right">{{ number_format($item->qty * $item->rate, 2) }}</td>
            <td class="center">{{ $item->cgst_percent }}</td>
            <td class="right">{{ number_format($item->cgst_amount, 2) }}</td>
            <td class="center">{{ $item->sgst_percent }}</td>
            <td class="right">{{ number_format($item->sgst_amount, 2) }}</td>
            @php
                $lineTotal = round($item->line_total, 2);
            @endphp
            <td class="right">{{ number_format($lineTotal, 2) }}</td>
        </tr>
        @endforeach

        @php
            /*
                A4 usable height ≈ 240mm
                Header + footer ≈ 135mm
                Remaining ≈ 105mm
                Row height ≈ 18px
                Max rows ≈ 8–9
            */
            $maxRows = 13;
            $currentRows = count($invoice->items);
            $emptyRows = max(0, $maxRows - $currentRows);
        @endphp

        @for($i = 0; $i < $emptyRows; $i++)
        <tr>
            <td>{{ __(' ') }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        @endfor
    </tbody>

    <tfoot>
        <tr>
            <th colspan="3" class="right">{{ __('Total') }}</th>
            <th class="right">{{ number_format($invoice->items->sum('qty'), 2) }}</th>
            <th></th>
            <th class="right">{{ number_format($invoice->taxable_amount, 2) }}</th>
            <th></th>
            <th class="right">{{ number_format($invoice->cgst_total, 2) }}</th>
            <th></th>
            <th class="right">{{ number_format($invoice->sgst_total, 2) }}</th>
            <th class="right">{{ number_format($invoice->grand_total, 2) }}</th>
        </tr>
    </tfoot>
</table>


{{-- ================= TOTALS + FOOTER ================= --}}
<table class="footer-wrap">
    <tr>
        <!-- LEFT BLOCK -->
        <td class="footer-left">
            <div class="words-title">{{ __('Total in words') }}</div>
            <div class="words-text">
                {{ NumberToWordsService::convert($invoice->grand_total) }}
            </div>

            <div class="terms">
                <b>{{ __('Terms & Conditions:') }}</b><br>
                @foreach(explode("\n", $invoice->vendor->terms_conditions) as $line)
                    {{ trim($line) }}<br>
                @endforeach
            </div>
        </td>

        <!-- RIGHT BLOCK -->
        <td class="footer-right">
            <table class="summary">
                <tr>
                    <td>{{ __('Taxable Amount') }}</td>
                    <td class="right">{{ number_format($invoice->taxable_amount,2) }}</td>
                </tr>
                <tr>
                    <td>{{ __('Add : CGST') }}</td>
                    <td class="right">{{ number_format($invoice->cgst_total,2) }}</td>
                </tr>
                <tr>
                    <td>{{ __('Add : SGST') }}</td>
                    <td class="right">{{ number_format($invoice->sgst_total,2) }}</td>
                </tr>
                @if($invoice->discount > 0)
                <tr>
                    <td>Discount ({{ (int) $invoice->discount_percent }}%)</td>
                    <td class="right">- {{ number_format($invoice->discount,2) }}</td>
                </tr>
                @endif
                <!-- <tr>
                    <td><strong>Total Tax</strong></td>
                    <td class="right"><strong>{{ number_format($invoice->cgst_total + $invoice->sgst_total,2) }}</strong></td>
                </tr> -->
                @if(abs((float)$invoice->round_off) > 0)
                <tr>
                    <td>{{ __('Total Before Round') }}</td>
                    <td class="right">{{ number_format($invoice->total_before_round, 2) }}</td>
                </tr>
                <tr>
                    <td>{{ __('Round Off') }}</td>
                    <td class="right">
                        {{ $invoice->round_off >= 0 ? '+' : '' }}
                        {{ number_format($invoice->round_off, 2) }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td><strong>{{ __('Grand Total') }}</strong></td>
                    <td class="right"><strong>{{ number_format($invoice->grand_total,2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="2" class="center">{{ __('( E & O.E )') }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="cert">
                        {{ __('Certified that the particulars given above are true and correct.') }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center for">
                        {{ __('For') }} <strong>{{ $invoice->vendor->company_name }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="sign"></td>
                </tr>
                <tr>
                    <td colspan="2" class="center sign-text">
                        {{ __('Authorised Signatory') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>



</body>
</html>
