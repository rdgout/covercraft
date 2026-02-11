<?php

namespace App\Jobs;

use App\Models\CoverageReport;
use App\Services\CloverParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCoverageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $coverageReportId) {}

    public function handle(CloverParser $parser): void
    {
        $report = CoverageReport::findOrFail($this->coverageReportId);

        // Get known repository files to help with path matching
        $repositoryFiles = $report->repository->fileCache()
            ->where('branch', $report->branch)
            ->first()
            ?->files ?? [];

        $cloverPath = Storage::disk(config('coverage.storage_disk'))->path($report->clover_file_path);
        $coverageData = $parser->parse($cloverPath, $repositoryFiles);

        DB::transaction(function () use ($report, $coverageData): void {
            CoverageReport::query()
                ->where('repository_id', $report->repository_id)
                ->where('branch', $report->branch)
                ->where('id', '!=', $report->id)
                ->where('archived', false)
                ->update(['archived' => true, 'archived_at' => now()]);

            $report->update([
                'coverage_percentage' => $coverageData['overall_percentage'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            foreach ($coverageData['files'] as $fileData) {
                $report->files()->create([
                    'file_path' => $fileData['path'],
                    'total_lines' => $fileData['total_lines'],
                    'covered_lines' => $fileData['covered_lines'],
                    'coverage_percentage' => $fileData['percentage'],
                    'line_coverage_data' => gzcompress(json_encode($fileData['lines'])),
                ]);
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        $report = CoverageReport::find($this->coverageReportId);

        if ($report) {
            $report->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
