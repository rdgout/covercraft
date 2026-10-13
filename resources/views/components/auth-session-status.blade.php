@props(['status'])
@if ($status)
    <div
        {{ $attributes->merge(['class' => 'cc-callout cc-callout--success']) }}
        role="status"
    >{{ $status }}</div>
@endif
