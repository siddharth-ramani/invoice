@props(['route', 'icon', 'label', 'active' => false])
<a href="{{ route($route) }}" class="app-nav-link {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif title="{{ $label }}" aria-label="{{ $label }}"><x-ui.icon :name="$icon" /><span>{{ $label }}</span>@if($active)<span class="app-nav-indicator" aria-hidden="true"></span>@endif</a>
