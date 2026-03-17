<?php

namespace App\Console\Commands;

use App\Actions\CacheRepositoryFilesAction;
use App\Contracts\GitHubServiceInterface;
use App\Models\Repository;
use Illuminate\Console\Command;

class FetchRepositoryFilesCommand extends Command
{
    protected $signature = 'coverage:fetch-files
                            {repository : The repository ID or owner/name slug}
                            {branch? : The branch name}
                            {commit? : The commit SHA}
                            {--latest : Fetch files for the latest coverage report}';

    protected $description = 'Fetch and cache repository files from GitHub';

    public function __construct(
        private GitHubServiceInterface $githubService,
        private CacheRepositoryFilesAction $cacheAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $repositoryArgument = $this->argument('repository');

        if (str_contains($repositoryArgument, '/')) {
            [$owner, $name] = explode('/', $repositoryArgument, 2);
            $repository = Repository::query()
                ->where('owner', $owner)
                ->where('name', $name)
                ->first();
        } else {
            $repository = Repository::find($repositoryArgument);
        }

        if (! $repository) {
            $this->error("Repository '{$repositoryArgument}' not found.");

            return self::FAILURE;
        }

        $this->info("Fetching files for {$repository->owner}/{$repository->name}...");

        if ($this->option('latest')) {
            return $this->handleLatest($repository);
        }

        $branch = $this->argument('branch');
        $commit = $this->argument('commit');

        if (! $branch || ! $commit) {
            $this->error('Both branch and commit are required, or use --latest flag.');

            return self::FAILURE;
        }

        return $this->fetchFiles($repository, $branch, $commit);
    }

    private function handleLatest(Repository $repository): int
    {
        $latestReport = $repository->coverageReports()
            ->where('archived', false)
            ->latest()
            ->first();

        if (! $latestReport) {
            $this->error('No coverage reports found for this repository.');

            return self::FAILURE;
        }

        $this->info("Using latest report: {$latestReport->branch} @ {$latestReport->commit_sha}");

        return $this->fetchFiles($repository, $latestReport->branch, $latestReport->commit_sha);
    }

    private function fetchFiles(Repository $repository, string $branch, string $commit): int
    {
        try {
            $files = $this->cacheAction->execute($repository, $branch, $commit);

            $this->info('✓ Successfully cached '.count($files).' files');
            $this->line('  Branch: '.$branch);
            $this->line('  Commit: '.$commit);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fetch files: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
