@props(['title', 'description', 'icon' => 'document'])
<div class="app-empty"><span class="app-empty-symbol"><x-ui.icon :name="$icon" /></span><h2>{{ $title }}</h2><p>{{ $description }}</p>@if(!$slot->isEmpty())<div>{{ $slot }}</div>@endif</div>
