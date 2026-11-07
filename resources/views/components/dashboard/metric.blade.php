@props(['label', 'value', 'hint', 'icon' => 'chart', 'accent' => 'blue'])
<article class="bp-metric bp-accent-{{ $accent }}">
    <div class="bp-metric-heading"><span>{{ $label }}</span><span class="bp-metric-icon"><x-dashboard.icon :name="$icon" /></span></div>
    <p class="bp-metric-value">{{ $value }}</p>
    <p class="bp-metric-hint">{{ $hint }}</p>
</article>
