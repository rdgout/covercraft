@props(['status'])
@php(
    $tone = match ($status) {
        'completed' => 'high',
        'failed' => 'low',
        default => 'mid'
    }
)
<span {{ $attributes->class(['cc-badge', 'cc-status', 'cc-badge--' . $tone]) }}>
    <x-icon
        :name="$status === 'completed' ? 'check' : ($status === 'failed' ? 'alert' : 'clock')"
        class="h-3 w-3"
    />{{ ucfirst($status) }}</span>
