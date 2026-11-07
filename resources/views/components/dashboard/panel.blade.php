@props(['title', 'description' => null])
<section {{ $attributes->class(['bp-panel']) }}>
    <div class="bp-panel-heading"><div><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>@isset($action)<div class="bp-panel-action">{{ $action }}</div>@endisset</div>
    {{ $slot }}
</section>
