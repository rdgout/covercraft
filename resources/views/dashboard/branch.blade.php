<x-app-layout>
    <header class="cc-detail-head">
        <a
            class="cc-link cc-back-link"
            href="{{ route('dashboard.repository', $repository) }}"
        >← {{ $repository->owner }}/{{ $repository->name }}</a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="cc-page-title">{{ $report->branch }}</h1>
            <x-coverage-badge
                :value="$report->coverage_percentage"
                size="lg"
            />
            <x-report-status :status="$report->status" />
        </div>
        <p class="cc-muted cc-caption">Commit <code>{{ substr($report->commit_sha, 0, 8) }}</code>
            @if ($report->pr_number)
                · Pull request #{{ $report->pr_number }}
            @endif
            · Uploaded {{ $report->created_at->diffForHumans() }}
        </p>
    </header>
    @if ($report->status === 'failed')
        <div
            class="cc-callout cc-callout--danger"
            role="alert"
        >
            <x-icon name="alert" />
            <div>
                <p class="cc-callout-title">This report could not be processed</p>
                <p>{{ $report->error_message ?? 'Check your CI pipeline and upload the report again.' }}</p>
            </div>
        </div>
    @elseif($report->status !== 'completed')
        <div
            class="cc-callout cc-callout--info"
            role="status"
        >
            <x-icon name="clock" />Coverage will be available when this report finishes processing.
        </div>
    @endif
    @if ($defaultBranchReport && $report->coverage_percentage !== null && $defaultBranchReport->coverage_percentage !== null)
        <section class="cc-comparison">
            <div class="cc-comparison-meters grid grid-cols-[minmax(80px,120px)_minmax(120px,1fr)] items-center gap-3">
                <span class="truncate text-sm font-semibold">{{ $report->branch }}</span>
                <x-coverage-meter :value="$report->coverage_percentage" />
                <span class="cc-muted truncate text-sm">{{ $repository->default_branch }}</span>
                <x-coverage-meter :value="$defaultBranchReport->coverage_percentage" />
            </div>
            <div class="cc-comparison-summary">
                <p class="cc-overline">vs {{ $repository->default_branch }}</p>
                <p class="mt-1 flex items-center gap-2 text-xl font-semibold">
                    <x-coverage-delta :value="$report->coverage_percentage - $defaultBranchReport->coverage_percentage" suffix="" />
                    points
                </p>
                <p class="cc-muted mt-1 text-xs">Compared with the latest default branch report.</p>
            </div>
        </section>
    @endif
    <x-patch-coverage-summary :report="$report" />
    <div class="cc-stats">
        <x-stat-tile
            label="Lines covered"
            :value="number_format($report->files->sum('covered_lines'))"
            :suffix="'/ ' . number_format($report->files->sum('total_lines'))"
        />
        <x-stat-tile
            label="Uncovered lines"
            :value="number_format($report->files->sum('total_lines') - $report->files->sum('covered_lines'))"
            hint="Room to grow your coverage"
        />
        <x-stat-tile
            label="Files in report"
            :value="$report->files->count()"
            :hint="$report->files->where('coverage_percentage', '<', 80)->count() . ' below the 80% target'"
        />
    </div>
    <div class="cc-branch-layout">
        <section
            class="cc-card cc-branch-files"
            x-data="fileTree"
        >
            <div class="cc-card-head">
                <div>
                    <h2 class="cc-card-title">Files</h2>
                    <p class="cc-card-sub">Folders show their combined coverage. Open one to dig in.</p>
                </div>
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="cc-btn cc-btn--ghost cc-btn--sm"
                        @click="expand(true)"
                    >Expand all</button>
                    <button
                        type="button"
                        class="cc-btn cc-btn--ghost cc-btn--sm"
                        @click="expand(false)"
                    >Collapse all</button>
                </div>
            </div>
            <div class="cc-toolbar">
                <div class="cc-field w-full sm:max-w-xs">
                    <label
                        for="file-search"
                        class="sr-only"
                    >Search files</label>
                    <input
                        id="file-search"
                        type="search"
                        placeholder="Find a file or directory…"
                        class="cc-input"
                        x-model="query"
                    >
                </div>
                <div class="flex gap-2">
                    <a
                        href="{{ route('dashboard.branch', [$repository, $report->branch, 'covered_only' => 1, ...$reportQuery]) }}"
                        @class([
                            'cc-btn cc-btn--sm',
                            'cc-btn--primary' => $showOnlyCovered,
                            'cc-btn--secondary' => !$showOnlyCovered,
                        ])
                        @if ($showOnlyCovered) aria-current="true" @endif
                    >Covered Only</a>
                    <a
                        href="{{ route('dashboard.branch', [$repository, $report->branch, 'covered_only' => 0, ...$reportQuery]) }}"
                        @class([
                            'cc-btn cc-btn--sm',
                            'cc-btn--primary' => !$showOnlyCovered,
                            'cc-btn--secondary' => $showOnlyCovered,
                        ])
                        @if (!$showOnlyCovered) aria-current="true" @endif
                    >All Files</a>
                </div>
            </div>
            @if (empty($fileTree))
                <div class="cc-empty">
                    <span class="cc-empty-icon">
                        <x-icon name="folder" />
                    </span>
                    <h3>No file tree data available</h3>
                    <p>Upload a completed report or push to this branch to populate the file cache.</p>
                </div>
            @else
                <div class="cc-tree">@include('dashboard.partials.tree', ['tree' => $fileTree, 'depth' => 0, 'prefix' => ''])
                </div>
                <p
                    class="cc-muted px-6 py-4 text-sm"
                    x-show="query && ![...$root.querySelectorAll('[data-file-path]')].some(el => matches(el.dataset.filePath))"
                    x-cloak
                >No files match your search.</p>
            @endif
        </section>
        @if ($defaultBranchReport)
            <section class="cc-panel cc-branch-changes space-y-4">
                <div>
                    <h2 class="cc-card-title">Changed on this branch</h2>
                    <p class="cc-card-sub">Files whose coverage moved compared with {{ $repository->default_branch }}.</p>
                </div>
                @forelse ($changedFiles as $changed)
                    <div class="flex flex-col gap-2 border-t pt-3" style="border-color: var(--border)">
                        <div class="flex items-center justify-between gap-2">
                            <a
                                href="{{ route('dashboard.file', [$repository, $report->branch, 'path' => $changed->file_path, ...$reportQuery]) }}"
                                class="cc-link cc-file-path min-w-0 truncate"
                                title="{{ $changed->file_path }}"
                            >{{ $changed->file_path }}</a>
                            @if ($defaultFiles->has($changed->file_path))
                                <x-coverage-delta :value="$changed->coverage_percentage - $defaultFiles->get($changed->file_path)->coverage_percentage" />
                            @else
                                <span class="cc-tag">New in report</span>
                            @endif
                        </div>
                        <x-coverage-meter :value="$changed->coverage_percentage" :label="$changed->file_path" size="sm" />
                    </div>
                @empty
                    <p class="cc-muted cc-caption">No file coverage differences in these reports.</p>
                @endforelse
            </section>
        @endif
    </div>
</x-app-layout>
