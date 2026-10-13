@props(['values' => [], 'width' => 120, 'height' => 36])
@php
    $values = collect($values)->filter(fn($value) => $value !== null)->values();
    $points = [];
    $range = max(1, $values->max() - $values->min());
    foreach ($values as $index => $value) {
        $points[] =
            round(4 + ($index * ($width - 8)) / max(1, $values->count() - 1), 1) .
            ',' .
            round($height - 4 - (($value - $values->min()) / $range) * ($height - 8), 1);
    }
    $tier = $values->last() >= 80 ? 'high' : ($values->last() >= 50 ? 'mid' : 'low');
@endphp
@if ($values->count() > 1)
    <svg
        {{ $attributes->class(['cc-spark', 'cc-spark--' . $tier]) }}
        width="{{ $width }}"
        height="{{ $height }}"
        viewBox="0 0 {{ $width }} {{ $height }}"
        role="img"
        aria-label="Coverage trend: {{ $values->map(fn($value) => number_format($value, 1) . '%')->join(', ') }}"
    >
        <polyline points="{{ implode(' ', $points) }}" />
        <circle
            cx="{{ explode(',', end($points))[0] }}"
            cy="{{ explode(',', end($points))[1] }}"
            r="3"
        />
    </svg>
@endif
