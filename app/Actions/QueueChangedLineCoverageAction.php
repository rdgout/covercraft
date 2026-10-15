<?php

namespace App\Actions;

use App\Jobs\CalculateChangedLineCoverageJob;
use App\Models\CoverageReport;

class QueueChangedLineCoverageAction
{
    public function execute(CoverageReport $report): void
    {
        $report->getConnection()->transaction(function () use ($report): void {
            $fresh = CoverageReport::query()->lockForUpdate()->find($report->id);
            if ($fresh === null || $fresh->diff_sha256 === null) {
                return;
            }
            if (in_array($fresh->patch_coverage['status'] ?? null, ['completed', 'no_executable_changes', 'incomplete', 'unavailable'], true)) {
                return;
            }
            if ($fresh->status === 'failed' || $fresh->diff_status === 'failed') {
                $fresh->update(['patch_coverage' => ['status' => 'unavailable', 'reason' => $fresh->status === 'failed' ? 'coverage_report_failed' : $fresh->diff_error_code]]);

                return;
            }
            if ($fresh->status !== 'completed' || $fresh->diff_status !== 'completed') {
                $fresh->update(['patch_coverage' => ['status' => $fresh->status !== 'completed' ? 'awaiting_coverage' : 'awaiting_diff']]);

                return;
            }
            $fresh->update(['patch_coverage' => ['status' => 'pending']]);
            CalculateChangedLineCoverageJob::dispatch($fresh->id)->afterCommit();
        });
    }
}
