@props(['title', 'description'])
<div class="bp-empty"><span class="bp-empty-icon"><x-dashboard.icon name="document" /></span><h3>{{ $title }}</h3><p>{{ $description }}</p>{{ $slot }}</div>
