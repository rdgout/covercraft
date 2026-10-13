@props(['messages'])
@if ($messages)
    <ul
        {{ $attributes->merge(['class' => 'cc-error']) }}
        role="alert"
    >
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
