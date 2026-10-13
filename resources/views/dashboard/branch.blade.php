<x-app-layout>
    <header class="space-y-3">
        <a
            class="cc-link text-sm"
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
        <p class="cc-muted text-sm">Commit <code>{{ substr($report->commit_sha, 0, 8) }}</code>
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
            <div class="grid min-w-0 flex-1 grid-cols-[minmax(80px,120px)_minmax(120px,1fr)] items-center gap-3">
                <span class="truncate text-sm font-semibold">{{ $report->branch }}</span>
                <x-coverage-meter :value="$report->coverage_percentage" />
                <span class="cc-muted truncate text-sm">{{ $repository->default_branch }}</span>
                <x-coverage-meter :value="$defaultBranchReport->coverage_percentage" />
            </div>
            <div>
                <p class="cc-overline">vs {{ $repository->default_branch }}</p>
                <p class="mt-1 flex items-center gap-2 text-xl font-semibold">
                    <x-coverage-delta :value="$report->coverage_percentage - $defaultBranchReport->coverage_percentage" />
                    points
                </p>
                <p class="cc-muted mt-1 text-xs">Compared with the latest default branch report.</p>
            </div>
        </section>
    @endif
    <div class="cc-stats">
        <x-stat-tile
            label="Covered lines"
            :value="number_format($report->files->sum('covered_lines'))"
            hint="Lines reached by your tests"
        />
        <x-stat-tile
            label="Uncovered lines"
            :value="number_format($report->files->sum('total_lines') - $report->files->sum('covered_lines'))"
            hint="Room to grow your coverage"
        />
        <x-stat-tile
            label="Files in report"
            :value="$report->files->count()"
            hint="Included in this coverage report"
        />
        <x-stat-tile
            label="Files below target"
            :value="$report->files->where('coverage_percentage', '<', 80)->count()"
            hint="Below 80% coverage"
        />
    </div>
    <section
        class="cc-card"
        x-data="fileTree"
    >
        <div class="cc-card-head">
            <div>
                <h2 class="cc-card-title">File coverage</h2>
                <p class="cc-card-sub">Explore your directories and find the lines that need a test.</p>
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
        <div class="cc-toolbar mt-4">
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
                    href="{{ route('dashboard.branch', [$repository, $report->branch, 'covered_only' => 1]) }}"
                    @class([
                        'cc-btn cc-btn--sm',
                        'cc-btn--primary' => $showOnlyCovered,
                        'cc-btn--secondary' => !$showOnlyCovered,
                    ])
                    @if ($showOnlyCovered) aria-current="true" @endif
                >Covered Only</a>
                <a
                    href="{{ route('dashboard.branch', [$repository, $report->branch, 'covered_only' => 0]) }}"
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
        <section class="cc-card">
            <div class="cc-card-head">
                <div>
                    <h2 class="cc-card-title">Coverage differences</h2>
                    <p class="cc-card-sub">Files with different coverage from {{ $repository->default_branch }}.</p>
                </div>
            </div>
            <div class="cc-card-body cc-card-body--flush">
                @if ($changedFiles->isEmpty())
                    <p class="cc-muted px-6 pb-6 text-sm">No file coverage differences in these reports.</p>
                @else
                    <div class="cc-table-scroll">
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>vs default</th>
                                    <th>Coverage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($changedFiles as $changed)
                                    <tr>
                                        <td>
                                            <a
                                                href="{{ route('dashboard.file', [$repository, $report->branch, 'path' => $changed->file_path]) }}"
                                                class="cc-link"
                                            >
                                                <code>{{ $changed->file_path }}</code>
                                            </a>
                                        </td>
                                        <td>
                                            @if ($defaultFiles->has($changed->file_path))
                                                <x-coverage-delta :value="$changed->coverage_percentage -
                                                    $defaultFiles->get($changed->file_path)->coverage_percentage" />
                                            @else
                                                <span class="cc-tag">New in
                                                    report</span>
                                            @endif
                                        </td>
                                        <td>
                                            <x-coverage-badge :value="$changed->coverage_percentage" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                @endif
            </div>
        </section>

    @endif
</x-app-layout>
