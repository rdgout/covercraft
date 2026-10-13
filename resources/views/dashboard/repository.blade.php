<x-app-layout>
    <header class="space-y-3">
        <a
            href="{{ route('dashboard') }}"
            class="cc-link text-sm"
        >← All repositories</a>
        <div class="cc-page-head">
            <div>
                <h1 class="cc-page-title">{{ $repository->owner }}/{{ $repository->name }}</h1>
                <p class="cc-muted mt-1 text-sm">Default branch <code>{{ $repository->default_branch }}</code> ·
                    {{ $repository->coverage_reports_count }} reports · Connected to GitHub</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('repositories.edit', $repository) }}"
                    class="cc-btn cc-btn--secondary"
                >Settings</a>
                <a
                    href="#readme-badge"
                    class="cc-btn cc-btn--strong"
                >Get README badge</a>
            </div>
        </div>
    </header>
    <div class="cc-stats">
        <section class="cc-panel flex flex-col gap-4 sm:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="cc-overline mb-2">Coverage on {{ $repository->default_branch }}</p>
                    <p class="cc-hero-value">
                        {{ $defaultBranchReport?->coverage_percentage === null ? '—' : number_format($defaultBranchReport->coverage_percentage, 1) }}
                        @if ($defaultBranchReport?->coverage_percentage !== null)
                            <span class="cc-number-suffix">%</span>
                        @endif
                    </p>
                </div>
                <x-sparkline
                    :values="$history->pluck('coverage_percentage')"
                    :width="200"
                    :height="56"
                />
            </div>
            <x-coverage-meter
                :value="$defaultBranchReport?->coverage_percentage"
                :label="'Coverage on ' . $repository->default_branch"
            />
            <p class="cc-muted text-sm">The tick marks the 80% coverage target.</p>
        </section>
        <x-stat-tile
            label="Branches on target"
            :value="$currentReports->where('status', 'completed')->where('coverage_percentage', '>=', 80)->count()" :suffix="'/ '.$currentReports->count()"
            hint="At least 80% coverage"
        />
        <x-stat-tile
            label="Uncovered lines"
            :value="$defaultBranchReport?->status === 'completed' ? number_format($defaultBranchReport->files->sum('total_lines') - $defaultBranchReport->files->sum('covered_lines')) : '—'"
            :hint="'On ' . $repository->default_branch"
        />
    </div>
    <section class="cc-card">
        <div class="cc-card-head">
            <div>
                <h2 class="cc-card-title">Branch leaderboard</h2>
                <p class="cc-card-sub">Default branch pinned first. Other branches ranked by coverage.</p>
            </div>
        </div>
        <div class="cc-card-body cc-card-body--flush">
            @if (!$defaultBranchReport && $branches->isEmpty())
                <div class="cc-empty">
                    <h3>No reports yet</h3>
                    <p>Upload your first coverage report to compare branches.</p>
                </div>
            @else
                <div class="cc-table-scroll">
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Coverage</th>
                                <th>vs {{ $repository->default_branch }}</th>
                                <th>Status</th>
                                <th>Commit</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (collect($defaultBranchReport ? [$defaultBranchReport] : [])->concat($branches->items()) as $branchReport)
                                <tr @class([
                                    'is-default' => $branchReport->branch === $repository->default_branch,
                                ])>
                                    <td>
                                        <a
                                            href="{{ route('dashboard.branch', [$repository, $branchReport->branch]) }}"
                                            class="cc-link cc-branch"
                                        >
                                            <x-icon name="branch" />{{ $branchReport->branch }}</a>
                                        @if ($branchReport->branch === $repository->default_branch)
                                            <span class="cc-tag ml-2">Default</span>
                                        @endif
                                    </td>
                                    <td class="min-w-[180px]">
                                        <x-coverage-meter :value="$branchReport->coverage_percentage" />
                                    </td>
                                    <td>
                                        <x-coverage-delta :value="$branchReport->coverage_percentage !== null &&
                                        $defaultBranchReport?->coverage_percentage !== null
                                            ? $branchReport->coverage_percentage -
                                                $defaultBranchReport->coverage_percentage
                                            : null" />
                                    </td>
                                    <td>
                                        <x-report-status :status="$branchReport->status" />
                                    </td>
                                    <td>
                                        <code class="cc-sha">{{ substr($branchReport->commit_sha, 0, 8) }}</code>
                                        @if ($branchReport->pr_number)
                                            <span class="cc-subtle ml-2">PR #{{ $branchReport->pr_number }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">{{ $branchReport->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-cursor-pagination :paginator="$branches" />

            @endif
        </div>
    </section>
    <div class="cc-two-columns">
        <section class="cc-panel space-y-4">
            <div>
                <h2 class="cc-card-title">Needs attention</h2>
                <p class="cc-card-sub">Failed reports and branches below the 80% target.</p>
            </div>
            @forelse($currentReports->filter(fn ($report) => $report->status === 'failed' || ($report->coverage_percentage !== null && $report->coverage_percentage < 80)) as $attention)
                <div
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl p-4"
                    style="background: var(--surface-sunken)"
                >
                    <div class="min-w-0">
                        <a
                            class="cc-link break-all"
                            href="{{ route('dashboard.branch', [$repository, $attention->branch]) }}"
                        >{{ $attention->branch }}</a>
                        <p class="cc-muted text-xs">
                            {{ $attention->status === 'failed' ? 'The last report failed. Check your CI pipeline.' : 'Below the coverage target.' }}
                        </p>
                    </div>
                    <x-coverage-badge :value="$attention->coverage_percentage" />
                </div>
            @empty<p class="cc-muted text-sm">
                    {{ $currentReports->isEmpty() ? 'Upload a report to see which branches need attention.' : 'No branches need attention. Keep it up!' }}
                </p>
            @endforelse
        </section>
        <section
            id="readme-badge"
            class="cc-panel space-y-4"
        >
            <div>
                <h2 class="cc-card-title">README badge</h2>
                <p class="cc-card-sub">Share your default branch’s latest coverage in your README.</p>
            </div>
            @php($badgeUrl = route('badge.show', ['owner' => $repository->owner, 'name' => $repository->name, 'branch' => $repository->default_branch]))
            @php($markdown = '![Coverage](' . $badgeUrl . ')')
            <img
                src="{{ $badgeUrl }}"
                alt="Coverage on {{ $repository->default_branch }}"
                width="140"
                height="20"
                loading="lazy"
            >
            <div class="flex items-center gap-2">
                <code class="cc-inline-code flex-1">{{ $markdown }}</code>
                <x-copy-button :value="$markdown" />
            </div>
        </section>
    </div>
</x-app-layout>
