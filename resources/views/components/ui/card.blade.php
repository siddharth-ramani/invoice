@props(['title' => null, 'description' => null])
<section {{ $attributes->class(['app-card']) }}>@if($title)<header class="app-card-header"><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</header>@endif<div class="app-card-body">{{ $slot }}</div></section>
