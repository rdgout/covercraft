<?php

namespace App\Http\Controllers;

use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Services\FileTreeBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private FileTreeBuilder $fileTreeBuilder) {}

    public function index(): View
    {
        $repositories = Repository::query()
            ->withCount('coverageReports')
            ->with('latestCoverageReport')
            ->get();

        return view('dashboard.index', compact('repositories'));
    }

    public function repository(Repository $repository): View
    {
        $branches = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->get();

        $defaultBranchReport = $branches->firstWhere('branch', $repository->default_branch);

        return view('dashboard.repository', compact('repository', 'branches', 'defaultBranchReport'));
    }

    public function branch(Repository $repository, string $branch): View
    {
        $report = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->with('files')
            ->firstOrFail();

        $defaultBranchReport = null;
        if ($branch !== $repository->default_branch) {
            $defaultBranchReport = CoverageReport::current()
                ->where('repository_id', $repository->id)
                ->where('branch', $repository->default_branch)
                ->first();
        }

        $repositoryFiles = RepositoryFileCache::query()
            ->where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->first()
            ?->files ?? [];

        $excludePatterns = config('coverage.exclude_patterns', []);
        $repositoryFiles = $this->fileTreeBuilder->applyExclusionPatterns($repositoryFiles, $excludePatterns);
        $fileTree = $this->fileTreeBuilder->build($report, $repositoryFiles);

        return view('dashboard.branch', compact('repository', 'report', 'defaultBranchReport', 'fileTree'));
    }

    public function file(Repository $repository, string $branch, Request $request): View
    {
        $filePath = $request->query('path');

        abort_unless($filePath, 400, 'File path is required');

        $report = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->firstOrFail();

        $file = $report->files()
            ->where('file_path', $filePath)
            ->firstOrFail();

        $lineCoverage = $file->line_coverage;

        return view('dashboard.file', compact('repository', 'report', 'file', 'lineCoverage', 'branch'));
    }
}
