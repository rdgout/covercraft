<x-app-layout>
    <header class="space-y-3">
        <a
            href="{{ route('repositories.index') }}"
            class="cc-link text-sm"
        >← Repositories</a>
        <h1 class="cc-page-title">Add repository</h1>
        <p class="cc-muted">Connect your project and make every line count.</p>
    </header>
    <div class="cc-two-columns">
        <section class="cc-panel">
            <form
                action="{{ route('repositories.store') }}"
                method="POST"
                class="space-y-5"
                x-data="repositoryForm(@js(['owner' => old('owner', ''), 'name' => old('name', ''), 'branch' => old('default_branch', 'main')]), @js(route('repositories.branches')))"
            >
                @csrf
                <div class="cc-field">
                    <x-input-label
                        for="team_id"
                        value="Team"
                    />
                    <select
                        name="team_id"
                        id="team_id"
                        class="cc-input"
                        required
                    >
                        <option value="">Select a team…</option>
                        @foreach ($teams as $team)
                            <option
                                value="{{ $team->id }}"
                                @selected(old('team_id', $teams->count() === 1 ? $teams->first()->id : null) == $team->id)
                            >{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('team_id')" />
                </div>
                @if (count($githubRepos))
                    <div class="cc-field">
                        <x-input-label
                            for="github_repo"
                            value="Choose from GitHub"
                        />
                        <select
                            id="github_repo"
                            class="cc-input"
                            @change="select($event.target.value)"
                        >
                            <option value="">Enter details manually</option>
                            @foreach ($githubRepos as $repo)
                                <option
                                    value="{{ json_encode(['owner' => $repo['owner'], 'name' => $repo['name'], 'branch' => $repo['default_branch']]) }}"
                                    @selected(old('owner') === $repo['owner'] && old('name') === $repo['name'])
                                >{{ $repo['full_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="cc-callout cc-callout--info">
                        <x-icon name="repo" />
                        <p>No GitHub token configured. Enter details manually.</p>
                    </div>

                @endif
                <div class="cc-field">
                    <x-input-label
                        for="owner"
                        value="Owner or organization"
                    />
                    <x-text-input
                        id="owner"
                        name="owner"
                        x-model="owner"
                        placeholder="e.g. acme"
                        required
                    />
                    <x-input-error :messages="$errors->get('owner')" />
                </div>
                <div class="cc-field">
                    <x-input-label
                        for="name"
                        value="Repository name"
                    />
                    <x-text-input
                        id="name"
                        name="name"
                        x-model="name"
                        placeholder="e.g. storefront"
                        required
                    />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div class="cc-field">
                    <x-input-label
                        for="default_branch"
                        value="Default branch"
                    />
                    <x-text-input
                        id="default_branch"
                        name="default_branch"
                        x-model="branch"
                        list="repository-branches"
                        required
                    />
                    <datalist id="repository-branches">
                        <template
                            x-for="item in branches"
                            :key="item"
                        >
                            <option :value="item">
                            </option>
                        </template>
                    </datalist>
                    <p
                        class="cc-hint"
                        x-show="loading"
                        x-cloak
                        role="status"
                    >Loading branches…</p>
                    <p
                        class="cc-hint"
                        x-show="error"
                        x-text="error"
                        x-cloak
                        role="status"
                    >
                    </p>
                    <p class="cc-hint">Other branches are compared with this branch.</p>
                    <x-input-error :messages="$errors->get('default_branch')" />
                </div>
                <div
                    class="flex gap-3 border-t pt-5"
                    style="border-color: var(--border)"
                >
                    <x-primary-button>Add Repository</x-primary-button>
                    <a
                        href="{{ route('repositories.index') }}"
                        class="cc-btn cc-btn--secondary"
                    >Cancel</a>
                </div>
            </form>
        </section>
        <aside class="space-y-4">
            <div class="cc-panel space-y-3">
                <span class="cc-empty-icon">
                    <x-icon name="branch" />
                </span>
                <h2 class="cc-card-title">A home for your coverage</h2>
                <p class="cc-muted text-sm">Upload Clover reports from your CI pipeline, compare branches, and explore
                    coverage down to each line.</p>
                <p class="cc-muted text-sm">Repositories belong to a team. Anyone in that team can see its reports.</p>
            </div>
            <div class="cc-callout cc-callout--info">
                <x-icon name="key" />
                <p>Create an API token after connecting your repository to authenticate uploads from CI.</p>
            </div>
        </aside>
    </div>
</x-app-layout>
