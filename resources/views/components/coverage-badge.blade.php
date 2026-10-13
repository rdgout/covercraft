@props(['value' => null, 'size' => 'sm'])
@php
    $tier = match (true) {
        $value === null => 'none',
        $value >= 80 => 'high',
        $value >= 50 => 'mid',
        default => 'low',
    };
@endphp
<span {{ $attributes->class(['cc-badge', 'cc-badge--' . $tier, 'cc-badge--lg' => $size === 'lg']) }}>
    <span
        class="cc-badge-dot"
        aria-hidden="true"
    >
    </span>{{ $value === null ? 'No data' : number_format($value, 1) . '%' }}
</span>
