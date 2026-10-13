<x-app-layout>
    <header class="cc-page-head">
        <div>
            <h1 class="cc-page-title">Repositories</h1>
            <p class="cc-muted mt-1">Your connected projects, all in one place.</p>
        </div>
        <a
            href="{{ route('repositories.create') }}"
            class="cc-btn cc-btn--primary"
        >
            <x-icon name="plus" />Add repository</a>
    </header>
    <section class="cc-card">
        @if ($repositories->isEmpty())
            <div class="cc-empty">
                <span class="cc-empty-icon">
                    <x-icon name="repo" />
                </span>
                <h3>No repositories linked yet.</h3>
                <p>Connect a GitHub repository to start tracking your test coverage.</p>
                <a
                    href="{{ route('repositories.create') }}"
                    class="cc-btn cc-btn--primary"
                >Add repository</a>
            </div>
        @else
            <div class="cc-table-scroll">
                <table class="cc-table">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Team</th>
                            <th>Default branch</th>
                            <th>Coverage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($repositories as $repository)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('dashboard.repository', $repository) }}"
                                        class="cc-link"
                                    >{{ $repository->owner }}/{{ $repository->name }}</a>
                                </td>
                                <td>{{ $repository->team->name }}</td>
                                <td>
                                    <span class="cc-branch">
                                        <x-icon name="branch" />
                                        <code>{{ $repository->default_branch }}</code>
                                    </span>
                                </td>
                                <td>
                                    <x-coverage-badge :value="$repository->latestCoverageReport?->coverage_percentage" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <a
                                            href="{{ route('repositories.edit', $repository) }}"
                                            class="cc-btn cc-btn--secondary cc-btn--sm"
                                        >Edit</a>
                                        <form
                                            action="{{ route('repositories.destroy', $repository) }}"
                                            method="POST"
                                            onsubmit="return confirm('Delete this repository and its coverage reports?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="cc-btn cc-btn--ghost cc-btn--sm cc-danger-text"
                                            >Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-cursor-pagination :paginator="$repositories" />

        @endif
    </section>
</x-app-layout>
