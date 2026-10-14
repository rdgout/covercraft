<nav
    class="cc-nav-shell"
    aria-label="Main navigation"
    x-data="{ menuOpen: false }"
    @keydown.escape.window="menuOpen = false"
>
    <div class="cc-nav-inner">
        <a
            href="{{ route('dashboard') }}"
            aria-label="CoverCraft dashboard"
        >
            <x-application-logo />
        </a>
        <div class="cc-nav-items">
            <a
                href="{{ route('dashboard') }}"
                class="cc-nav-item"
                @if (request()->routeIs('dashboard')) aria-current="page" @endif
            >Dashboard</a>
            <a
                href="{{ route('repositories.index') }}"
                class="cc-nav-item"
                @if (request()->routeIs('repositories.*', 'dashboard.repository', 'dashboard.branch', 'dashboard.file')) aria-current="page" @endif
            >Repositories</a>
            <a
                href="{{ route('tokens.index') }}"
                class="cc-nav-item"
                @if (request()->routeIs('tokens.*')) aria-current="page" @endif
            >API tokens</a>
        </div>
        <div class="ml-auto flex items-center gap-3">
            <button
                type="button"
                class="cc-btn cc-btn--ghost cc-btn--sm"
                data-theme-toggle
                aria-label="Toggle color theme"
            >
                <x-icon name="theme" />
            </button>
            <div
                class="relative"
                @click.outside="menuOpen = false"
            >
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-full"
                    @click="menuOpen = !menuOpen"
                    :aria-expanded="menuOpen"
                    aria-controls="account-menu"
                    aria-label="Account menu for {{ auth()->user()->name }}"
                >
                    @php($initials = Str::of(auth()->user()->name)->squish()->explode(' ')->take(2)->map(fn (string $name) => mb_strtoupper(mb_substr($name, 0, 1)))->implode(''))
                    <span class="cc-avatar" aria-hidden="true">{{ $initials }}</span>
                    <span class="hidden text-sm font-semibold sm:inline">{{ auth()->user()->name }}</span>
                    <x-icon name="chevron" />
                </button>
                <div
                    id="account-menu"
                    class="cc-account-menu"
                    x-show="menuOpen"
                    x-cloak
                >
                    <a
                        href="{{ route('profile.edit') }}"
                        class="cc-menu-item"
                        @if (request()->routeIs('profile.*')) aria-current="page" @endif
                    >Profile & settings</a>
                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="cc-menu-item"
                        >Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
