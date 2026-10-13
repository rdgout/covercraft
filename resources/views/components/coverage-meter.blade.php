@props(['value' => null, 'label' => 'Coverage', 'showValue' => true])
@php($tier = $value === null ? 'none' : ($value >= 80 ? 'high' : ($value >= 50 ? 'mid' : 'low')))
<div {{ $attributes->class(['cc-meter', 'cc-meter--' . $tier]) }}>
    <div
        class="cc-meter-track"
        @if ($value !== null) role="meter" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}" 
@else
 role="img" aria-label="{{ $label }}: no data" @endif
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
