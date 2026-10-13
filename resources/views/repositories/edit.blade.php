<x-app-layout>
    <header class="space-y-3">
        <a
            href="{{ route('repositories.index') }}"
            class="cc-link text-sm"
        >← Repositories</a>
        <h1 class="cc-page-title">Repository settings</h1>
        <p class="cc-muted">{{ $repository->owner }}/{{ $repository->name }}</p>
    </header>
    <div class="cc-two-columns">
        <section class="cc-panel">
            <h2 class="cc-card-title mb-5">Coverage settings</h2>
            <form
                action="{{ route('repositories.update', $repository) }}"
                method="POST"
                class="space-y-5"
            >
                @csrf
                @method('PUT')
                <div class="cc-field">
                    <x-input-label
                        for="default_branch"
                        value="Default branch"
                    />
                    <x-text-input
                        name="default_branch"
                        id="default_branch"
                        :value="old('default_branch', $repository->default_branch)"
                        required
                    />
                    <p class="cc-hint">The baseline used to compare your branches.</p>
                    <x-input-error :messages="$errors->get('default_branch')" />
                </div>
                <div class="flex gap-3">
                    <x-primary-button>Update</x-primary-button>
                    <a
                        href="{{ route('dashboard.repository', $repository) }}"
                        class="cc-btn cc-btn--secondary"
                    >View coverage</a>
                </div>
            </form>
        </section>
        <section class="cc-panel space-y-4">
            <h2 class="cc-card-title">GitHub connection</h2>
            <a
                href="{{ $repository->github_url }}"
                class="cc-link inline-flex items-center gap-2"
                target="_blank"
                rel="noopener noreferrer"
            >{{ $repository->owner }}/{{ $repository->name }}<x-icon name="external" />
            </a>
            <div class="cc-field">
                <span class="cc-label">Webhook secret</span>
                <div class="flex items-center gap-2">
                    <code class="cc-inline-code flex-1">{{ $repository->webhook_secret ?? 'Not set' }}</code>
                    @if ($repository->webhook_secret)
                        <x-copy-button :value="$repository->webhook_secret" />
                    @endif
                </div>
                <p class="cc-hint">Use this secret to verify GitHub webhook deliveries.</p>
            </div>
        </section>
    </div>
</x-app-layout>
