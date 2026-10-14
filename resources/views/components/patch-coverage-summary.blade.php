@props(['report'])
@php
    $coverage = $report->patch_coverage;
    $status = $coverage['status'] ?? 'not_calculated';
    $message = match ($status) {
        'completed' => 'Coverage of reported executable lines added or modified in this diff.',
        'no_executable_changes' => 'No executable changes in the measured source files.',
        'incomplete' => 'Some changed source files have no usable coverage data. The overall percentage is unavailable.',
        'awaiting_diff' => $report->diff_sha256 ? 'The uploaded diff is being processed.' : 'Diff not uploaded. Attach a diff from CI to measure changed-line coverage.',
        'awaiting_coverage' => 'Waiting for the coverage report to finish processing.',
        'pending' => 'Changed-line coverage is being calculated.',
        'unavailable' => $report->diffErrorMessage() ?? 'Changed-line coverage is unavailable for this report.',
        default => 'Changed-line coverage has not been calculated for this report.',
    };
    $filePaths = $report->files->pluck('file_path')->flip();
@endphp
<section class="cc-card" aria-label="Coverage of changed lines">
    <div class="cc-card-head">
        <div>
            <h2 class="cc-card-title">Coverage of changed lines</h2>
            <p class="cc-card-sub">{{ $message }}</p>
        </div>
        @if ($status === 'completed')
            <x-coverage-badge :value="$coverage['percentage']" size="lg" />
        @endif
    </div>
    @if (in_array($status, ['completed', 'incomplete', 'no_executable_changes'], true))
        <div class="cc-toolbar">
            <span>{{ number_format($coverage['covered_lines']) }} of {{ number_format($coverage['total_lines']) }} measured changed executable lines covered</span>
            <span class="cc-muted cc-caption">{{ number_format($coverage['total_lines'] - $coverage['covered_lines']) }} uncovered</span>
        </div>
        <div class="px-6 py-4 cc-muted cc-caption">
            <p>Baseline {{ $coverage['base_branch'] ?? 'commit' }} <code>{{ substr($coverage['base_sha'] ?? '', 0, 8) }}</code> → <code>{{ substr($coverage['head_sha'] ?? '', 0, 8) }}</code></p>
            <p>Measured source: {{ implode(', ', $coverage['scope']['include_patterns'] ?? ['*.php']) }}. Excluded: {{ implode(', ', $coverage['scope']['exclude_patterns'] ?? []) }}.</p>
        </div>
        @if (($coverage['files'] ?? []) !== [])
            <div class="cc-table-scroll">
                <table class="cc-table">
                    <thead><tr><th>Changed source file</th><th>Covered changed lines</th><th>Coverage</th></tr></thead>
                    <tbody>
                        @foreach ($coverage['files'] as $changed)
                            <tr>
                                <td>
                                    @if ($filePaths->has($changed['path']))
                                        <a class="cc-link cc-file-path" href="{{ route('dashboard.file', [$report->repository_id, $report->branch, 'path' => $changed['path'], 'report' => $report->id]) }}">{{ $changed['path'] }}</a>
                                    @else
                                        @php($sourceUrl = rtrim($report->repository->github_url, '/') . '/blob/' . rawurlencode($report->commit_sha) . '/' . implode('/', array_map('rawurlencode', explode('/', $changed['path']))))
                                        <a class="cc-link cc-file-path" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $changed['path'] }}</a>
                                    @endif
                                </td>
                                <td>{{ $changed['covered_lines'] }} / {{ $changed['total_lines'] }}</td>
                                <td>
                                    @if ($changed['status'] === 'incomplete')
                                        <span class="cc-muted">Coverage data unavailable</span>
                                    @elseif ($changed['status'] === 'no_executable_changes')
                                        <span class="cc-muted">No executable changes</span>
                                    @else
                                        <x-coverage-badge :value="$changed['percentage']" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($coverage['files_truncated'] ?? false)
            <p class="px-6 py-4 cc-muted cc-caption">Showing the first {{ count($coverage['files']) }} of {{ $coverage['file_count'] }} changed source files. Totals include all measured files. Open a file from the tree to inspect its changes.</p>
        @endif
    @endif
</section>
