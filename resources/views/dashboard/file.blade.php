<x-app-layout class="cc-main--file" x-data="lineViewer">
    @php
        $lineCount = max(count($sourceLines), empty($lineCoverage) ? 0 : max(array_keys($lineCoverage)));
        $directory = dirname($file->file_path);
        $directoryPrefix = $directory === '.' ? '' : $directory . '/';
        $githubUrl = rtrim($repository->github_url, '/') . '/blob/' . rawurlencode($report->commit_sha) . '/' . implode('/', array_map('rawurlencode', explode('/', $file->file_path)));
    @endphp
    <header class="cc-detail-head">
        <a
            class="cc-link cc-back-link"
            href="{{ route('dashboard.branch', [$repository, $branch, ...$reportQuery]) }}"
        >← {{ $branch }}</a>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <h1 class="cc-file-title"><span class="cc-subtle">{{ $directoryPrefix }}</span>{{ basename($file->file_path) }}</h1>
                <div class="cc-muted cc-caption flex flex-wrap items-center gap-3">
                    <x-coverage-badge :value="$file->coverage_percentage" suffix="covered" />
                    <span>{{ $file->covered_lines }} of {{ $file->total_lines }} executable lines</span>
                    @if ($patchFile && $patchFile['status'] !== 'incomplete')
                        <span>Changed executable lines: {{ $patchFile['covered_lines'] }} / {{ $patchFile['total_lines'] }}</span>
                    @endif
                    @if ($coverageDelta !== null)
                        <x-coverage-delta :value="$coverageDelta" />
                        <span>vs {{ $repository->default_branch }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ $githubUrl }}" class="cc-btn cc-btn--secondary" target="_blank" rel="noopener noreferrer">
                <x-icon name="arrow-right" />Open on GitHub
            </a>
        </div>
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
    @if ($uncoveredRanges !== [])
        <div class="flex flex-wrap items-center gap-2" aria-label="Jump to uncovered lines">
            <span class="cc-overline mr-1">Jump to uncovered</span>
            @foreach (array_slice($uncoveredRanges, 0, 7) as $range)
                <a href="#L{{ $range['start'] }}" class="cc-uncovered-link">{{ $range['start'] === $range['end'] ? $range['start'] : $range['start'] . '–' . $range['end'] }}</a>
            @endforeach
            <button
                type="button"
                class="cc-btn cc-btn--secondary cc-btn--sm ml-auto"
                @click="next"
                :disabled="!lines.length"
            >Next uncovered line ↓</button>
        </div>
    @endif
    @if (($patchFile['uncovered_ranges'] ?? []) !== [])
        <div class="flex flex-wrap items-center gap-2" aria-label="Uncovered changed lines">
            <span class="cc-overline">Uncovered changed lines</span>
            @foreach (array_slice($patchFile['uncovered_ranges'], 0, 7) as $range)
                <a href="#L{{ $range['start'] }}" class="cc-uncovered-link">{{ $range['start'] === $range['end'] ? $range['start'] : $range['start'] . '–' . $range['end'] }}</a>
            @endforeach
            <button type="button" class="cc-btn cc-btn--secondary cc-btn--sm" @click="nextChanged" :disabled="!changedLines.length">Next uncovered changed line ↓</button>
        </div>
    @endif
    <section
        class="cc-card"
    >
        <div class="cc-source-toolbar">
            <span>{{ number_format($lineCount) }} source lines · Commit <code>{{ substr($report->commit_sha, 0, 8) }}</code></span>
            <div class="flex flex-wrap items-center gap-4">
                <span class="inline-flex items-center gap-1.5"><span class="cc-source-swatch" aria-hidden="true"></span>Covered</span>
                <span class="inline-flex items-center gap-1.5"><span class="cc-source-swatch cc-source-swatch--uncovered" aria-hidden="true"></span>Not covered</span>
            </div>
        </div>
        <div class="cc-table-scroll py-2">
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
                    @php
                        $changedRangeIndex = 0;
                    @endphp
                    @for ($lineNumber = 1; $lineNumber <= $lineCount; $lineNumber++)
                        @php
                            $lineData = $lineCoverage[$lineNumber] ?? null;
                            $sourceLine = $sourceLines[$lineNumber - 1] ?? (empty($sourceLines) ? '(Source code unavailable - showing coverage data only)' : '');
                            while (isset($changedLineRanges[$changedRangeIndex]) && $changedLineRanges[$changedRangeIndex]['end'] < $lineNumber) {
                                $changedRangeIndex++;
                            }
                            $isChanged = isset($changedLineRanges[$changedRangeIndex]) && $changedLineRanges[$changedRangeIndex]['start'] <= $lineNumber;
                        @endphp
                        <tr
                            id="L{{ $lineNumber }}"
                            data-changed="{{ $isChanged ? 'true' : 'false' }}"
                            @class([
                                'is-covered' => $lineData !== null && $lineData['covered'],
                                'is-uncovered' => $lineData !== null && !$lineData['covered'],
                            ])
                        >
                            <td class="cc-ln">
                                <a
                                    href="#L{{ $lineNumber }}"
                                    aria-label="Line {{ $lineNumber }}"
                                >{{ $lineNumber }}@if ($isChanged)<span class="sr-only"> — Changed line</span><span aria-hidden="true"> +</span>@endif</a>
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
    </section>
</x-app-layout>
