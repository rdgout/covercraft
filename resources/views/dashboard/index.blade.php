<x-app-layout>
    <header class="cc-page-head">
        <div class="space-y-1">
            <p class="cc-overline">{{ now()->format('l, j F') }}</p>
            <h1 class="cc-welcome-title">Hello, {{ Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="cc-muted">{{ $summary['on_target_count'] }} of {{ $summary['branch_count'] }} branches are on
                target. A clearer picture of what needs attention.</p>
        </div>
        <a
            href="{{ route('repositories.create') }}"
            class="cc-btn cc-btn--primary"
        >
            <x-icon name="plus" />Add repository</a>
    </header>
    <div class="cc-stats">
        <x-stat-tile
            label="Average coverage"
            :value="$summary['average_coverage'] === null ? '—' : number_format($summary['average_coverage'], 1)"
            :suffix="$summary['average_coverage'] === null ? '' : '%'"
            hint="Across default branches"
        />
        <x-stat-tile
            label="Branches on target"
            :value="$summary['on_target_count']"
            :suffix="'/ ' . $summary['branch_count']"
            hint="At least 80% coverage"
        />
        <x-stat-tile
            label="Repositories"
            :value="$summary['repository_count']"
            hint="Connected to your teams"
        />
        <x-stat-tile
            label="Failed reports"
            :value="$summary['failed_count']"
            hint="In the last 24 hours"
        />
    </div>
    @if (auth()->user()->teams->count() > 1)
        <form
            method="GET"
            action="{{ route('dashboard') }}"
            class="flex flex-wrap items-center gap-3"
        >
            <label
                for="team"
                class="cc-label"
            >Team</label>
            <select
                id="team"
                name="team"
                class="cc-input max-w-xs"
            >
                <option value="all">All teams</option>
                @foreach (auth()->user()->teams as $team)
                    <option
                        value="{{ $team->id }}"
                        @selected($selectedTeamId == $team->id)
                    >{{ $team->name }}</option>
                @endforeach
            </select>
            <x-secondary-button type="submit">Apply</x-secondary-button>
        </form>

    @endif
    <section class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="cc-card-title">Repositories</h2>
            <span class="cc-muted text-sm">Coverage on the default
                branch</span>
        </div>
        @if ($repositories->isEmpty())
            <div class="cc-card cc-empty">
                <span class="cc-empty-icon">
                    <x-icon name="repo" />
                </span>
                <h3>No repositories found</h3>
                <p>Connect a repository and upload your first report to see your coverage here.</p>
                <a
                    href="{{ route('repositories.create') }}"
                    class="cc-btn cc-btn--primary"
                >Add repository</a>
            </div>
        @else
            <div class="cc-repos">
                @foreach ($repositories as $repository)
                    @php
                        $current = $repository->currentCoverageReports;
                        $lowest = $current
                            ->where('status', 'completed')
                            ->whereNotNull('coverage_percentage')
                            ->sortBy('coverage_percentage')
                            ->first();
                        $coverage = $repository->latestCoverageReport?->coverage_percentage;

                    @endphp
                    <article class="cc-panel flex flex-col gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a
                                    class="cc-link break-words text-base"
                                    href="{{ route('dashboard.repository', $repository) }}"
                                >{{ $repository->owner }}/{{ $repository->name }}</a>
                                <p class="cc-subtle mt-1 text-xs">{{ $current->count() }} branches ·
                                    {{ $current->where('status', 'completed')->where('coverage_percentage', '>=', 80)->count() }}
                                    on target</p>
                            </div>
                            <span class="cc-tag shrink-0">{{ $repository->default_branch }}</span>
                        </div>
                        <div class="flex items-end justify-between gap-2">
                            <span class="cc-big-value">{{ $coverage === null ? '—' : number_format($coverage, 1) }}
                                @if ($coverage !== null)
                                    <span class="cc-number-suffix">%</span>
                                @endif
                            </span>
                            <x-sparkline :values="$repository->defaultBranchHistory->reverse()->pluck('coverage_percentage')" />
                        </div>
                        <x-coverage-meter
                            :value="$coverage"
                            :show-value="false"
                            :label="'Coverage on ' . $repository->default_branch"
                        />
                        <div
                            class="cc-muted flex items-center justify-between gap-2 border-t pt-3 text-xs"
                            style="border-color: var(--border)"
                        >
                            @if ($lowest)
                                <span class="truncate">Lowest:
                                    <code>{{ $lowest->branch }}</code>
                                </span>
                                <x-coverage-badge :value="$lowest->coverage_percentage" />
                            @else
                                <span>Waiting for your first completed report</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <x-cursor-pagination :paginator="$repositories" />

        @endif
    </section>
    <section class="cc-card">
        <div class="cc-card-head">
            <h2 class="cc-card-title">Latest reports</h2>
            <span class="cc-subtle text-xs">Across your repositories</span>
        </div>
        <div class="cc-card-body cc-card-body--flush">
            @if ($latestReports->isEmpty())
                <div class="cc-empty">
                    <h3>Your coverage story starts here</h3>
                    <p>Upload a Clover report from your CI pipeline to track progress.</p>
                </div>
            @else
                <div class="cc-table-scroll">
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th>Repository</th>
                                <th>Branch</th>
                                <th>Coverage</th>
                                <th>vs default</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latestReports as $latest)
                                <tr>
                                    <td>
                                        <a
                                            class="cc-link"
                                            href="{{ route('dashboard.repository', $latest->repository) }}"
                                        >{{ $latest->repository->owner }}/{{ $latest->repository->name }}</a>
                                    </td>
                                    <td>
                                        <a
                                            class="cc-link"
                                            href="{{ route('dashboard.branch', [$latest->repository, $latest->branch]) }}"
                                        >{{ $latest->branch }}</a>
                                    </td>
                                    <td>
                                        <x-coverage-badge :value="$latest->coverage_percentage" />
                                    </td>
                                    <td>
                                        <x-coverage-delta :value="$latest->coverage_percentage !== null &&
                                        $latest->repository->latestCoverageReport?->coverage_percentage !== null
                                            ? $latest->coverage_percentage -
                                                $latest->repository->latestCoverageReport->coverage_percentage
                                            : null" />
                                    </td>
                                    <td>
                                        <x-report-status :status="$latest->status" />
                                    </td>
                                    <td class="whitespace-nowrap">{{ $latest->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @endif
        </div>
    </section>
</x-app-layout>
