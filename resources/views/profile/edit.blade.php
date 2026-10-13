<x-app-layout>
    <header>
        <h1 class="cc-page-title">Profile & settings</h1>
        <p class="cc-muted mt-1">Your account, just the way you like it.</p>
    </header>
    <div class="cc-two-columns">
        <div class="space-y-6">
            <div class="cc-panel">@include('profile.partials.update-profile-information-form')
            </div>
            <div class="cc-panel">@include('profile.partials.update-password-form')
            </div>
        </div>
        <div class="space-y-6">
            <section class="cc-panel space-y-4">
                <h2 class="cc-card-title">Appearance</h2>
                <p class="cc-muted text-sm">Choose a lighter canvas or a darker view for your coverage.</p>
                <button
                    type="button"
                    class="cc-btn cc-btn--secondary"
                    data-theme-toggle
                >
                    <x-icon name="theme" />Toggle color theme</button>
            </section>
            <div class="cc-panel">@include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
