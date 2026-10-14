@props(['value' => null, 'suffix' => '%'])
@php
    $roundedValue = $value === null ? null : round((float) $value, 1);
    $tone = match (true) {
        $roundedValue === null => 'none',
        $roundedValue > 0 => 'up',
        $roundedValue < 0 => 'down',
        default => 'flat',
    };
@endphp
<span {{ $attributes->class(['cc-delta', 'cc-delta--' . $tone]) }}>
    @if ($value !== null)
        @if ($tone !== 'flat')
            <x-icon :name="$tone === 'up' ? 'arrow-up' : 'arrow-down'" width="14" height="14" />
        @endif
        {{ $roundedValue > 0 ? '+' : ($roundedValue < 0 ? '−' : '±') }}{{ number_format(abs($roundedValue), 1) }}{{ $suffix }}<span class="sr-only"> percentage points</span>
    @else
        —
    @endif
</span>
