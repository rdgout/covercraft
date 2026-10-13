<x-app-layout>
    <div
        class="contents"
        x-data="{ creating: @js($errors->has('name') || $errors->has('team_id')) }"
    >
        <header class="cc-page-head">
            <div>
                <h1 class="cc-page-title">API tokens</h1>
                <p class="cc-muted mt-1">Connect your CI pipeline to CoverCraft.</p>
            </div>
            <x-primary-button
                type="button"
                @click="creating = !creating"
                x-bind:aria-expanded="creating"
                aria-controls="create-token"
            >
                <x-icon name="plus" />Create New Token</x-primary-button>
        </header>
        @if (session('token'))
            <div
                class="cc-callout cc-callout--warning"
                role="status"
            >
                <x-icon name="key" />
                <div class="min-w-0 flex-1">
                    <p class="cc-callout-title">Save this token now — it will not be shown again.</p>
                    <div class="mt-3 flex items-center gap-2">
                        <code class="cc-inline-code flex-1">{{ session('token') }}</code>
                        <x-copy-button :value="session('token')" />
                    </div>
                </div>
            </div>
        @endif
        @if ($teams->count() > 1)
            <form
                action="{{ route('tokens.index') }}"
                method="GET"
                class="flex flex-wrap items-center gap-3"
            >
                <x-input-label
                    for="team-filter"
                    value="Team"
                />
                <select
                    id="team-filter"
                    name="team"
                    class="cc-input max-w-xs"
                >
                    <option value="">All teams</option>
                    @foreach ($teams as $team)
                        <option
                            value="{{ $team->id }}"
                            @selected($selectedTeamId == $team->id)
                        >{{ $team->name }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Apply</x-secondary-button>
            </form>

        @endif
        <section
            id="create-token"
            class="cc-panel"
            x-show="creating"
            @if (!$errors->has('name') && !$errors->has('team_id')) x-cloak @endif
        >
            <h2 class="cc-card-title mb-5">Create an API token</h2>
            <form
                action="{{ route('tokens.store') }}"
                method="POST"
                class="max-w-xl space-y-5"
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
                        required
                        class="cc-input"
                    >
                        <option value="">Select a team</option>
                        @foreach ($teams as $team)
                            <option
                                value="{{ $team->id }}"
                                @selected(old('team_id', $selectedTeamId ?: ($teams->count() === 1 ? $teams->first()->id : null)) == $team->id)
                            >{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('team_id')" />
                </div>
                <div class="cc-field">
                    <x-input-label
                        for="name"
                        value="Token name"
                    />
                    <x-text-input
                        name="name"
                        id="name"
                        :value="old('name')"
                        placeholder="e.g. GitHub Actions"
                        required
                    />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div class="flex gap-2">
                    <x-primary-button>Create Token</x-primary-button>
                    <x-secondary-button @click="creating = false">Cancel</x-secondary-button>
                </div>
            </form>
        </section>
        <section class="cc-card">
            @if ($tokens->isEmpty())
                <div class="cc-empty">
                    <span class="cc-empty-icon">
                        <x-icon name="key" />
                    </span>
                    <h3>No tokens created yet</h3>
                    <p>Create your first token to upload coverage from your CI pipeline.</p>
                    <x-primary-button
                        type="button"
                        @click="creating = true"
                    >Create a token</x-primary-button>
                </div>
            @else
                <div class="cc-table-scroll">
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Team</th>
                                <th>Created by</th>
                                <th>Created</th>
                                <th>Last used</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr>
                                    <td class="font-semibold">{{ $token->name }}</td>
                                    <td>{{ $token->team->name }}</td>
                                    <td>{{ $token->createdBy?->name ?? 'N/A' }}</td>
                                    <td class="whitespace-nowrap">{{ $token->created_at->format('j M Y') }}</td>
                                    <td>{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td>
                                        <form
                                            action="{{ route('tokens.destroy', $token) }}"
                                            method="POST"
                                            onsubmit="return confirm('Revoke this token? CI pipelines using it will no longer be able to upload coverage.')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="cc-btn cc-btn--ghost cc-btn--sm cc-danger-text"
                                            >Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @endif
        </section>
        <div class="cc-callout cc-callout--info">
            <x-icon name="key" />
            <p>Tokens allow coverage uploads for all repositories in their team. Keep them in your CI secret store and
                revoke tokens you no longer use.</p>
        </div>
    </div>
</x-app-layout>
