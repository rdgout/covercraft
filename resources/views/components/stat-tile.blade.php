@props(['label', 'value', 'suffix' => '', 'hint' => ''])
<div {{ $attributes->merge(['class' => 'cc-stat']) }}>
    <span class="cc-overline">{{ $label }}</span>
    <span class="cc-stat-value">{{ $value }}@if ($suffix)
            <small>{{ $suffix }}</small>
        @endif
    </span>
    @if ($hint)
        <span class="cc-stat-foot">{{ $hint }}</span>
    @endif
</div>
