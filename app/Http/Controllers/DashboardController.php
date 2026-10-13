<?php

namespace App\Http\Controllers;

use App\Contracts\GitHubServiceInterface;
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
            ->with(['latestCoverageReport', 'currentCoverageReports', 'defaultBranchHistory' => fn ($query) => $query->limit(8)])
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(15)
            ->withQueryString();

        $reportsQuery = CoverageReport::query()
            ->whereHas('repository', fn ($query) => $query->forTeams($teamIds));
        $currentReportsQuery = (clone $reportsQuery)->current();
        $summary = [
            'repository_count' => Repository::query()->forTeams($teamIds)->count(),
            'branch_count' => (clone $currentReportsQuery)->count(),
            'on_target_count' => (clone $currentReportsQuery)->where('status', 'completed')->where('coverage_percentage', '>=', 80)->count(),
            'average_coverage' => (clone $currentReportsQuery)->where('status', 'completed')
                ->whereHas('repository', fn ($query) => $query->whereColumn('repositories.default_branch', 'coverage_reports.branch'))
                ->avg('coverage_percentage'),
            'failed_count' => (clone $reportsQuery)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
        ];
        $latestReports = $reportsQuery->with('repository.latestCoverageReport')->latest()->orderByDesc('id')->limit(6)->get();

        return view('dashboard.index', compact('repositories', 'selectedTeamId', 'summary', 'latestReports'));
    }

    public function repository(ViewRepositoryRequest $request, Repository $repository): View
    {
        $repository->loadCount('coverageReports');
        $history = $repository->defaultBranchHistory()->limit(8)->get()->reverse()->values();
        $currentReports = $repository->currentCoverageReports()->get();
        $defaultBranchReport = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->where('branch', $repository->default_branch)
            ->first();

        $branches = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->where('branch', '!=', $repository->default_branch)
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(20);

        $defaultBranchReport?->load('files');

        return view('dashboard.repository', compact('repository', 'branches', 'defaultBranchReport', 'history', 'currentReports'));
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

        $showOnlyCovered = $request->boolean('covered_only', true);
        $repositoryFiles = array_values(array_unique(array_merge($repositoryFiles, $report->files->pluck('file_path')->all())));
        $repositoryFiles = $this->fileTreeBuilder->applyExclusionPatterns($repositoryFiles, $excludePatterns);
        $fileTree = $this->fileTreeBuilder->build($report, $repositoryFiles, $showOnlyCovered);

        $defaultBranchReport?->load('files');
        $defaultFiles = $defaultBranchReport?->files->keyBy('file_path');
        $changedFiles = $defaultFiles === null ? collect() : $report->files->filter(function ($file) use ($defaultFiles): bool {
            $defaultFile = $defaultFiles->get($file->file_path);

            return $defaultFile === null || (float) $file->coverage_percentage !== (float) $defaultFile->coverage_percentage;
        });

        return view('dashboard.branch', compact('repository', 'report', 'defaultBranchReport', 'fileTree', 'showOnlyCovered', 'changedFiles', 'defaultFiles'));
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
        $githubService = app(GitHubServiceInterface::class);
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
