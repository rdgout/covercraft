@props(['value' => null])
@php($tone = $value === null ? 'none' : ($value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat')))
<span {{ $attributes->class(['cc-delta', 'cc-delta--' . $tone]) }}>
    @if ($value !== null)
        <span aria-hidden="true">{{ $value > 0 ? '↗' : ($value < 0 ? '↘' : '→') }}</span>
        {{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}<span class="sr-only"> percentage points</span>
    @else
        —
    @endif
</span>
