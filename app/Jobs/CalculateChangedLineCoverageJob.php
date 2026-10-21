<?php

namespace App\Jobs;

use App\Actions\QueueChangedLineCoverageAction;
use App\Models\CoverageReport;
use App\Services\PatchCoverageCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CalculateChangedLineCoverageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $coverageReportId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('changed-line-coverage:'.$this->coverageReportId))->releaseAfter(10)->expireAfter(90)];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(PatchCoverageCalculator $calculator, QueueChangedLineCoverageAction $ready): void
    {
        $report = CoverageReport::query()->find($this->coverageReportId);
        if ($report === null || in_array($report->patch_coverage['status'] ?? null, ['completed', 'no_executable_changes', 'incomplete', 'unavailable'], true)) {
            return;
        }
        if ($report->status !== 'completed' || $report->diff_status !== 'completed') {
            $ready->execute($report);

            return;
        }
        $result = $calculator->calculate($report);
        $report->getConnection()->transaction(function () use ($report, $result): void {
            $fresh = CoverageReport::query()->lockForUpdate()->find($report->id);
            if ($fresh === null || $fresh->diff_sha256 !== $result['artifact_sha256']
                || $fresh->status !== 'completed' || $fresh->diff_status !== 'completed'
                || in_array($fresh->patch_coverage['status'] ?? null, ['completed', 'no_executable_changes', 'incomplete', 'unavailable'], true)) {
                return;
            }
            $fresh->update(['patch_coverage' => $result]);
            PostPullRequestCommentJob::dispatch($fresh->id)->afterCommit();
        });
    }

    public function failed(Throwable $exception): void
    {
        CoverageReport::query()->whereKey($this->coverageReportId)
            ->where('patch_coverage->status', 'pending')
            ->update(['patch_coverage' => json_encode(['status' => 'unavailable', 'reason' => 'calculation_failed'], JSON_THROW_ON_ERROR)]);
    }
}
