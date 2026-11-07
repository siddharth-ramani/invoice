@props(['href' => null, 'variant' => 'primary', 'icon' => null, 'type' => 'submit'])
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['btn', 'btn-'.$variant]) }}>@if($icon)<x-ui.icon :name="$icon" />@endif<span>{{ $slot }}</span></a>
@else
<button type="{{ $type }}" {{ $attributes->class(['btn', 'btn-'.$variant]) }}>@if($icon)<x-ui.icon :name="$icon" />@endif<span>{{ $slot }}</span></button>
@endif
