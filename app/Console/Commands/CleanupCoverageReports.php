<?php

namespace App\Console\Commands;

use App\Models\CoverageReport;
use App\Models\Repository;
use App\Services\GitHubBranchStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CleanupCoverageReports extends Command
{
    protected $signature = 'coverage:cleanup
                            {--dry-run : Preview cleanup without changing reports or files}
                            {--repository= : Only clean up this repository ID}';

    protected $description = 'Prune superseded coverage reports and reports for confirmed merged branches';

    private bool $hasFailures = false;

    /**
     * Execute the console command.
     */
    public function handle(GitHubBranchStatusService $github): int
    {
        $this->hasFailures = false;

        foreach (['default_branch_reports', 'failed_report_days', 'merged_branch_days'] as $setting) {
            if (filter_var(config("coverage.retention.{$setting}"), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error("coverage.retention.{$setting} must be a positive integer.");

                return self::FAILURE;
            }
        }

        $repositories = Repository::query();

        if ($this->option('repository') !== null) {
            if (! ctype_digit((string) $this->option('repository'))) {
                $this->error('The repository option must be a repository ID.');

                return self::FAILURE;
            }

            $repositories->whereKey($this->option('repository'));

            if (! $repositories->exists()) {
                $this->error('Repository not found.');

                return self::FAILURE;
            }
        }

        if (! $github->isConfigured()) {
            $this->warn('GitHub is not configured; keeping the latest reports for all feature branches.');
        }

        $reportCount = 0;
        $fileCount = 0;

        foreach ($repositories->lazyById() as $repository) {
            $this->line("Inspecting {$repository->owner}/{$repository->name}...");
            $reports = $this->reports($repository);
            $latestReports = $reports->unique('branch');
            $mergedBranches = $this->mergedBranches($github, $repository, $reports);
            $freshReports = $this->reports($repository);

            $mergedBranches = array_values(array_filter($mergedBranches, fn (string $branch): bool => $freshReports->firstWhere('branch', $branch)?->id === $latestReports->firstWhere('branch', $branch)?->id
            ));

            $candidates = $this->candidates($repository, $freshReports, $mergedBranches);
            $deleted = 0;

            foreach ($candidates as $report) {
                if ($this->option('dry-run') || $this->deleteReport($report, in_array($report->branch, $mergedBranches, true))) {
                    $deleted++;
                    $fileCount += $report->files_count;
                }
            }

            if (! $this->option('dry-run')) {
                foreach ($mergedBranches as $branch) {
                    if ($repository->coverageReports()->where('branch', $branch)->doesntExist()) {
                        $repository->fileCache()->where('branch', $branch)->delete();
                    }
                }
            }

            $reportCount += $deleted;
            $this->line("{$repository->owner}/{$repository->name}: {$deleted} reports ".($this->option('dry-run') ? 'would be removed.' : 'removed.'));
        }

        $verb = $this->option('dry-run') ? 'Would remove' : 'Removed';
        $this->info("{$verb} {$reportCount} reports and {$fileCount} coverage files.");

        return $this->hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, CoverageReport>
     */
    private function reports(Repository $repository): Collection
    {
        return $repository->coverageReports()->withCount('files')->latest()->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, CoverageReport>  $reports
     * @return list<string>
     */
    private function mergedBranches(GitHubBranchStatusService $github, Repository $repository, Collection $reports): array
    {
        $mergedBefore = now()->subDays((int) config('coverage.retention.merged_branch_days'));
        $branches = $reports->groupBy('branch')
            ->reject(fn (Collection $branchReports): bool => $branchReports->contains(fn (CoverageReport $report): bool => ! in_array($report->status, ['completed', 'failed'], true)))
            ->map(fn (Collection $branchReports): CoverageReport => $branchReports->first())
            ->filter(fn (CoverageReport $report): bool => $report->branch !== $repository->default_branch && $report->created_at->lessThanOrEqualTo($mergedBefore))
            ->values();

        try {
            return $github->mergedBranches($repository, $branches, $mergedBefore);
        } catch (Throwable $exception) {
            report($exception);
            $this->warn("Could not verify merges for {$repository->owner}/{$repository->name}; keeping branch reports.");
            $this->hasFailures = true;

            return [];
        }
    }

    /**
     * @param  Collection<int, CoverageReport>  $reports
     * @param  list<string>  $mergedBranches
     * @return Collection<int, CoverageReport>
     */
    private function candidates(Repository $repository, Collection $reports, array $mergedBranches): Collection
    {
        $protectedIds = $reports->unique('branch')->modelKeys();
        $successful = $reports->where('status', 'completed');
        $protectedIds = array_merge($protectedIds, $successful->unique('branch')->modelKeys(), $successful
            ->where('branch', $repository->default_branch)
            ->take((int) config('coverage.retention.default_branch_reports'))
            ->modelKeys());

        $failedBefore = now()->subDays((int) config('coverage.retention.failed_report_days'));

        return $reports->filter(function (CoverageReport $report) use ($mergedBranches, $protectedIds, $failedBefore): bool {
            if (! in_array($report->status, ['completed', 'failed'], true)) {
                return false;
            }

            if (in_array($report->branch, $mergedBranches, true)) {
                return true;
            }

            if (! $report->archived || in_array($report->id, $protectedIds, true)) {
                return false;
            }

            return $report->status === 'completed' || $report->created_at->lessThan($failedBefore);
        });
    }

    private function deleteReport(CoverageReport $report, bool $mergedBranch): bool
    {
        try {
            return $report->getConnection()->transaction(function () use ($report, $mergedBranch): bool {
                $freshReport = CoverageReport::query()->lockForUpdate()->find($report->id);

                if (! $freshReport || ! in_array($freshReport->status, ['completed', 'failed'], true)) {
                    return false;
                }

                if (! $mergedBranch && ! $freshReport->archived) {
                    return false;
                }

                $sharedPath = CoverageReport::query()
                    ->where('clover_file_path', $freshReport->clover_file_path)
                    ->whereKeyNot($freshReport->id)
                    ->exists();

                if (! $sharedPath) {
                    $disk = Storage::disk(config('coverage.storage_disk'));

                    if ($disk->exists($freshReport->clover_file_path) && ! $disk->delete($freshReport->clover_file_path)) {
                        throw new RuntimeException('Could not delete the Clover file.');
                    }
                }

                return $freshReport->delete();
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->warn("Could not remove report {$report->id}; it will be retried on the next cleanup.");
            $this->hasFailures = true;

            return false;
        }
    }
}
