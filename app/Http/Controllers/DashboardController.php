<?php

namespace App\Http\Controllers;

use App\Http\Requests\ViewRepositoryRequest;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Services\FileTreeBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private FileTreeBuilder $fileTreeBuilder) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $selectedTeamId = $request->query('team');

        if ($selectedTeamId && $user->teams()->where('teams.id', $selectedTeamId)->exists()) {
            $teamIds = collect([$selectedTeamId]);
            session(['selected_team' => $selectedTeamId]);
        } else {
            $teamIds = $user->getTeamIds();
            $selectedTeamId = session('selected_team', 'all');
        }

        $repositories = Repository::query()
            ->forTeams($teamIds)
            ->withCount('coverageReports')
            ->with('latestCoverageReport')
            ->get();

        return view('dashboard.index', compact('repositories', 'selectedTeamId'));
    }

    public function repository(ViewRepositoryRequest $request, Repository $repository): View
    {
        $branches = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->get();

        $defaultBranchReport = $branches->firstWhere('branch', $repository->default_branch);

        return view('dashboard.repository', compact('repository', 'branches', 'defaultBranchReport'));
    }

    public function branch(ViewRepositoryRequest $request, Repository $repository, string $branch): View
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

        $showOnlyCovered = $request->boolean('covered_only', false);
        $fileTree = $this->fileTreeBuilder->build($report, $repositoryFiles, $showOnlyCovered);

        return view('dashboard.branch', compact('repository', 'report', 'defaultBranchReport', 'fileTree', 'showOnlyCovered'));
    }

    public function file(ViewRepositoryRequest $request, Repository $repository, string $branch): View
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

        // Fetch file contents from GitHub
        $githubService = app(\App\Services\GitHubService::class);
        $sourceLines = [];
        $error = null;

        try {
            $fileContents = $githubService->fetchFileContents($repository, $report->commit_sha, $filePath);
            $sourceLines = explode("\n", $fileContents);
        } catch (\Exception $e) {
            $error = 'Failed to fetch file contents from GitHub: '.$e->getMessage();
        }

        return view('dashboard.file', compact('repository', 'report', 'file', 'lineCoverage', 'branch', 'sourceLines', 'error'));
    }
}
