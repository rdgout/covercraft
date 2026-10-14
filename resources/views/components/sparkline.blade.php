@props(['values' => [], 'width' => 96, 'height' => 28])
@php
    $values = collect($values)->filter(fn($value) => $value !== null)->values();
    $points = [];
    $minimum = $values->min() ?? 0;
    $maximum = $values->max() ?? 0;
    if ($maximum - $minimum < 2) {
        $minimum -= 1;
        $maximum += 1;
    }
    $range = $maximum - $minimum;
    foreach ($values as $index => $value) {
        $points[] =
            round(3 + ($index * ($width - 6)) / max(1, $values->count() - 1), 1) .
            ',' .
            round(3 + (1 - ($value - $minimum) / $range) * ($height - 6), 1);
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
@else
    <span class="cc-delta cc-delta--none" aria-label="No coverage trend available">—</span>
@endif
