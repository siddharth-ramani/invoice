@props(['status'])
@php
    $labels = ['paid' => __('Marked paid'), 'unpaid' => __('Marked unpaid'), 'cancelled' => __('Cancelled')];
    $tone = array_key_exists($status, $labels) ? $status : 'unknown';
@endphp
<span class="bp-badge bp-badge-{{ $tone }}">{{ $labels[$status] ?? __('Unknown status') }}</span>
