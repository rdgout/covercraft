<x-app-layout>
    <header class="space-y-3">
        <a
            class="cc-link text-sm"
            href="{{ route('dashboard.branch', [$repository, $branch]) }}"
        >← {{ $repository->owner }}/{{ $repository->name }} / {{ $branch }}</a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="cc-page-title break-all">{{ $file->file_path }}</h1>
            <x-coverage-badge
                :value="$file->coverage_percentage"
                size="lg"
            />
        </div>
        <p class="cc-muted text-sm">{{ $file->covered_lines }}/{{ $file->total_lines }} lines covered · Commit
            <code>{{ substr($report->commit_sha, 0, 8) }}</code> · Uploaded {{ $report->created_at->diffForHumans() }}
        </p>
    </header>
    @if ($error)
        <div
            class="cc-callout cc-callout--warning"
            role="status"
        >
            <x-icon name="alert" />
            <div>
                <p class="cc-callout-title">Source code unavailable</p>
                <p>{{ $error }}</p>
                <p>Coverage data is still shown below.</p>
            </div>
        </div>
    @endif
    <section
        class="cc-card"
        x-data="lineViewer"
    >
        <div class="cc-toolbar">
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <span class="cc-badge cc-badge--high">
                    <span
                        class="cc-badge-dot"
                        aria-hidden="true"
                    >
                    </span>Covered</span>
                <span class="cc-badge cc-badge--low">
                    <span
                        class="cc-badge-dot"
                        aria-hidden="true"
                    >
                    </span>Uncovered</span>
                <span class="cc-subtle">— Not executable</span>
            </div>
            <button
                type="button"
                class="cc-btn cc-btn--secondary cc-btn--sm"
                @click="next"
                :disabled="!lines.length"
            >Next uncovered line ↓</button>
        </div>
        <div class="cc-table-scroll">
            <table
                class="cc-source"
                aria-label="Source code and line coverage"
            >
                <thead class="sr-only">
                    <tr>
                        <th>Line</th>
                        <th>Hits</th>
                        <th>Source code</th>
                    </tr>
                </thead>
                <tbody>
                    @php($lineCount = max(count($sourceLines), empty($lineCoverage) ? 0 : max(array_keys($lineCoverage))))
                    @for ($lineNumber = 1; $lineNumber <= $lineCount; $lineNumber++)
                        @php($lineData = $lineCoverage[$lineNumber] ?? null)
                        @php($sourceLine = $sourceLines[$lineNumber - 1] ?? (empty($sourceLines) ? '(Source code unavailable - showing coverage data only)' : ''))
                        <tr
                            id="L{{ $lineNumber }}"
                            @class([
                                'is-covered' => $lineData !== null && $lineData['covered'],
                                'is-uncovered' => $lineData !== null && !$lineData['covered'],
                            ])
                        >
                            <td class="cc-ln">
                                <a
                                    href="#L{{ $lineNumber }}"
                                    aria-label="Line {{ $lineNumber }}"
                                >{{ $lineNumber }}</a>
                            </td>
                            <td class="cc-hits">{{ $lineData === null ? '—' : $lineData['count'] . '×' }}</td>
                            <td class="cc-code">{{ $sourceLine }}</td>
                        </tr>
                    @endfor
                    @if ($lineCount === 0)
                        <tr>
                            <td
                                colspan="3"
                                class="p-6 text-center"
                            >No coverage data available.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="cc-pager">
            <span>{{ number_format($file->covered_lines) }} covered ·
                {{ number_format($file->total_lines - $file->covered_lines) }} uncovered
                lines</span>
            <span>{{ basename($file->file_path) }}</span>
        </div>
    </section>
</x-app-layout>
