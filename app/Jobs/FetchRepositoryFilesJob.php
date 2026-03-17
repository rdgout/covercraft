<?php

namespace App\Jobs;

use App\Contracts\GitHubServiceInterface;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchRepositoryFilesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $repositoryId,
        public string $branch,
        public string $commitSha,
    ) {}

    public function handle(GitHubServiceInterface $githubService): void
    {
        $repository = Repository::findOrFail($this->repositoryId);

        $githubService->getOrFetchRepositoryFiles($repository, $this->branch, $this->commitSha);
    }
}
