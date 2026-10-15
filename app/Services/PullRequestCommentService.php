<?php

namespace App\Services;

use App\Models\CoverageReport;

class PullRequestCommentService
{
    /**
     * @param  list<string>  $changedFiles
     */
    public function buildCommentBody(CoverageReport $report, ?CoverageReport $baseReport, array $changedFiles): string
    {
        $headCoverage = number_format((float) $report->coverage_percentage, 2);
        $headBranch = $this->markdown($report->branch);

        if ($baseReport) {
            $baseCoverage = number_format((float) $baseReport->coverage_percentage, 2);
            $baseBranch = $this->markdown($baseReport->branch);
            $diff = (float) $report->coverage_percentage - (float) $baseReport->coverage_percentage;

            if ($diff > 0) {
                $emoji = '✅';
                $diffStr = '(+'.number_format($diff, 2).'%)';
            } elseif ($diff < 0) {
                $emoji = '⚠️';
                $diffStr = '('.number_format($diff, 2).'%)';
            } else {
                $emoji = '➡️';
                $diffStr = '(+0.00%)';
            }

            $headRow = '| Head '.$emoji.' | `'.$headBranch.'` | '.$headCoverage.'% '.$diffStr.' |';
            $baseRow = '| Base | `'.$baseBranch.'` | '.$baseCoverage.'% |';
        } else {
            $headRow = '| Head | `'.$headBranch.'` | '.$headCoverage.'% |';
            $baseRow = '| Base | — | — |';
        }

        $reportUrl = route('dashboard.branch', [
            'repository' => $report->repository_id,
            'branch' => $report->branch,
            'report' => $report->id,
        ]);

        $lines = [
            '<!-- covercraft-report -->',
            '## CoverCraft Coverage Report',
            '',
            '| | Branch | Coverage |',
            '|---|---|---|',
            $baseRow,
            $headRow,
        ];

        $fileRows = $this->buildChangedFilesRows($report, $changedFiles);
        if ($fileRows !== []) {
            $lines[] = '';
            $lines[] = '### Changed Files';
            $lines[] = '';
            $lines[] = '| File | Coverage |';
            $lines[] = '|---|---|';
            foreach ($fileRows as $row) {
                $lines[] = $row;
            }
        }

        foreach ($this->patchSummary($report) as $line) {
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = "_View full report on [CoverCraft]({$reportUrl})_";

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function patchSummary(CoverageReport $report): array
    {
        $coverage = $report->patch_coverage;
        if ($coverage === null) {
            return [];
        }
        $status = $coverage['status'];
        $summary = match ($status) {
            'completed' => number_format($coverage['percentage'], 2).'% ('.$coverage['covered_lines'].'/'.$coverage['total_lines'].' executable lines); '.($coverage['total_lines'] - $coverage['covered_lines']).' uncovered.',
            'no_executable_changes' => 'No executable changes in measured source files.',
            'incomplete' => 'Coverage data incomplete; the overall percentage is unavailable.',
            'unavailable' => 'Unavailable. '.($report->diffErrorMessage() ?? 'The inputs could not be processed.'),
            'awaiting_diff' => $report->diff_sha256 === null ? 'Diff not uploaded.' : 'Processing the uploaded diff.',
            'awaiting_coverage' => 'Waiting for Clover processing.',
            default => 'Calculation pending.',
        };
        $lines = ['', '### Changed-line coverage', '', $summary];
        if (isset($coverage['base_sha'])) {
            $lines[] = '';
            $lines[] = 'Branch baseline: `'.$this->markdown($coverage['base_branch'] ?? 'commit').'` at `'.substr($coverage['base_sha'], 0, 8).'` → uploaded `'.substr($report->commit_sha, 0, 8).'`.';
        }
        if (($coverage['files'] ?? []) !== []) {
            $lines = array_merge($lines, ['', '| File | Covered changed lines | Coverage |', '|---|---|---|']);
            foreach (array_slice($coverage['files'], 0, 25) as $file) {
                $value = $file['percentage'] === null ? ($file['status'] === 'incomplete' ? 'Data unavailable' : 'No executable changes') : number_format($file['percentage'], 2).'%';
                $lines[] = '| `'.$this->markdown($file['path']).'` | '.$file['covered_lines'].'/'.$file['total_lines'].' | '.$value.' |';
            }
            if (($coverage['file_count'] ?? count($coverage['files'])) > 25) {
                $lines[] = '';
                $lines[] = 'Showing 25 of '.$coverage['file_count'].' changed source files; totals include all measured files.';
            }
        }

        return $lines;
    }

    private function markdown(string $value): string
    {
        return str_replace(["\r", "\n", '`', '|'], [' ', ' ', '&#96;', '&#124;'], $value);
    }

    /**
     * @param  list<string>  $changedFiles
     * @return list<string>
     */
    private function buildChangedFilesRows(CoverageReport $report, array $changedFiles): array
    {
        if (empty($changedFiles)) {
            return [];
        }

        $rows = [];

        foreach ($report->files as $file) {
            if (in_array($file->file_path, $changedFiles, true)) {
                $coverage = number_format((float) $file->coverage_percentage, 2);
                $rows[] = '| `'.$this->markdown($file->file_path).'` | '.$coverage.'% |';
            }
        }

        return $rows;
    }
}
