@props(['months'])
@php
    $values = array_column($months, 'sales');
    $max = max(1, max($values));
    $min = min(0, min($values));
    $span = $max - $min;
    $points = [];
    foreach ($months as $index => $month) {
        $x = count($months) === 1 ? 360 : 64 + ($index / (count($months) - 1)) * 620;
        $y = 184 - (($month['sales'] - $min) / $span) * 152;
        $points[] = round($x, 2).','.round($y, 2);
    }
    $zeroY = round(184 - ((0 - $min) / $span) * 152, 2);
@endphp
<div class="bp-chart">
    <svg viewBox="0 0 720 230" role="img" aria-label="{{ __('Monthly invoice sales chart. Exact values are available in the monthly breakdown below.') }}">
        @foreach([0, 1, 2, 3] as $tick)
        @php $y = 32 + $tick * (152 / 3); $value = $max - $tick * ($span / 3); @endphp
        <line x1="64" y1="{{ $y }}" x2="684" y2="{{ $y }}" class="bp-chart-grid" />
        <text x="52" y="{{ $y + 4 }}" text-anchor="end" class="bp-chart-label bp-chart-value-label">{{ abs($value) >= 10000000 ? round($value / 10000000, 1).__('Cr') : (abs($value) >= 100000 ? round($value / 100000, 1).'L' : (abs($value) >= 1000 ? round($value / 1000, 1).'k' : round($value))) }}</text>
        @endforeach
        <polygon points="{{ explode(',', $points[0])[0] }},{{ $zeroY }} {{ implode(' ', $points) }} {{ explode(',', $points[count($points)-1])[0] }},{{ $zeroY }}" class="bp-chart-area" />
        <polyline points="{{ implode(' ', $points) }}" class="bp-chart-line" />
        @foreach($months as $index => $month)
        @php [$x, $y] = explode(',', $points[$index]); @endphp
        <circle cx="{{ $x }}" cy="{{ $y }}" r="3" class="bp-chart-dot"><title>{{ $month['label'] }}: ₹{{ number_format($month['sales'], 2) }}</title></circle>
        @if($index === 0 || $index === count($months)-1 || ($index % max(1, (int) ceil(count($months) / 6)) === 0 && $index < count($months)-2))
        <text x="{{ $x }}" y="216" text-anchor="middle" class="bp-chart-label {{ $index === 0 ? 'bp-chart-first' : ($index === count($months)-1 ? 'bp-chart-last' : 'bp-chart-middle') }}">{{ $month['label'] }}</text>
        @endif
        @endforeach
    </svg>
    <details class="bp-chart-data"><summary>{{ __('View monthly breakdown') }}</summary><div class="bp-table-scroll" tabindex="0" role="region" aria-label="{{ __('Monthly invoice sales') }}"><table class="bp-table"><thead><tr><th scope="col">{{ __('Month') }}</th><th scope="col" class="bp-number">{{ __('Invoice sales') }}</th></tr></thead><tbody>@foreach($months as $month)<tr><th scope="row">{{ $month['label'] }}</th><td class="bp-number">₹{{ number_format($month['sales'], 2) }}</td></tr>@endforeach</tbody></table></div></details>
</div>
