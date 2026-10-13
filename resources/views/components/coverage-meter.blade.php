@props(['value' => null, 'label' => 'Coverage', 'showValue' => true])
@php
    $tier = match (true) {
        $value === null => 'none',
        $value >= 80 => 'high',
        $value >= 50 => 'mid',
        default => 'low',
    };
@endphp
<div {{ $attributes->class(['cc-meter', 'cc-meter--' . $tier]) }}>
    <div
        class="cc-meter-track"
        role="{{ $value === null ? 'img' : 'meter' }}"
        aria-label="{{ $value === null ? $label.': no data' : $label }}"
        @if ($value !== null)
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $value }}"
        @endif
    >
        <span
            class="cc-meter-fill"
            style="width: {{ min(100, max(0, $value ?? 0)) }}%"
        >
        </span>
        <span
            class="cc-meter-target"
            style="left: 80%"
            title="80% target"
            aria-hidden="true"
        >
        </span>
    </div>
    @if ($showValue)
        <span class="cc-meter-value">{{ $value === null ? '—' : number_format($value, 1) . '%' }}</span>
    @endif
</div>
