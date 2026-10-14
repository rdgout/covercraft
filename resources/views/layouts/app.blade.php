<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>@include('layouts.head')
</head>

<body class="antialiased">
    <a
        class="cc-skip"
        href="#main-content"
    >Skip to content</a>
    @include('layouts.navigation')
    <main
        id="main-content"
        {{ $attributes->class(['cc-main']) }}
    >
        @isset($header)
            <header>{{ $header }}</header>
        @endisset
        @if (session('success'))
            <div
                class="cc-callout cc-callout--success"
                role="status"
            >
                <x-icon name="check" />{{ session('success') }}
            </div>
        @endif
        {{ $slot }}
    </main>
</body>

</html>
