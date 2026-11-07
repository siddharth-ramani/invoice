@props(['title', 'description' => null, 'eyebrow' => null])
<header class="app-page-header"><div>@if($eyebrow)<p class="bp-eyebrow">{{ $eyebrow }}</p>@endif<h1>{{ $title }}</h1>@if($description)<p>{{ $description }}</p>@endif</div>@if(!$slot->isEmpty())<div class="app-page-actions">{{ $slot }}</div>@endif</header>
