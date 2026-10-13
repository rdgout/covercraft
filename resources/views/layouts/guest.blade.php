<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>@include('layouts.head')
</head>

<body class="antialiased">
    @php
        $headings = [
            'login' => ['Welcome back', 'Your next coverage win starts here.'],
            'register' => ['Make every line count', 'Create your account and start tracking coverage.'],
            'password.request' => ['Forgot your password?', 'We’ll help you get back to your repositories.'],
            'password.reset' => ['A fresh start', 'Choose a new password for your account.'],
            'password.confirm' => ['Confirm your password', 'A quick check before you continue.'],
            'verification.notice' => ['Check your inbox', 'Verify your email to finish setting up your account.'],
        ];
        [$heading, $description] = $headings[request()->route()->getName()] ?? [
            'Welcome to CoverCraft',
            'A clearer picture of your test coverage.',
        ];

    @endphp
    <button
        type="button"
        class="cc-btn cc-btn--ghost absolute right-4 top-4"
        data-theme-toggle
        aria-label="Toggle color theme"
    >
        <x-icon name="theme" />
    </button>
    <main class="cc-auth">
        <a href="{{ route('dashboard') }}">
            <x-application-logo />
        </a>
        <div class="cc-auth-card">
            <header class="mb-6">
                <h1 class="cc-page-title">{{ $heading }}</h1>
                <p class="cc-muted mt-2 text-sm">{{ $description }}</p>
            </header>
            {{ $slot }}
            @if (request()->routeIs('login'))
                <p
                    class="cc-muted mt-6 border-t pt-6 text-center text-sm"
                    style="border-color: var(--border)"
                >New here? <a
                        href="{{ route('register') }}"
                        class="cc-link"
                    >Create an account</a>
                </p>
            @elseif(request()->routeIs('register', 'password.request', 'password.reset'))
                <p class="mt-6 text-center text-sm">
                    <a
                        href="{{ route('login') }}"
                        class="cc-link"
                    >Back to log in</a>
                </p>
            @endif
        </div>
        <p class="cc-subtle text-xs">CoverCraft · Every line counts.</p>
    </main>
</body>

</html>
