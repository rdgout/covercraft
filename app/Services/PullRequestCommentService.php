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
        $headBranch = $report->branch;

        if ($baseReport) {
            $baseCoverage = number_format((float) $baseReport->coverage_percentage, 2);
            $baseBranch = $baseReport->branch;
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

        $lines[] = '';
        $lines[] = "_View full report on [CoverCraft]({$reportUrl})_";

        return implode("\n", $lines);
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
                $rows[] = '| `'.$file->file_path.'` | '.$coverage.'% |';
            }
        }

        return $rows;
    }
}
