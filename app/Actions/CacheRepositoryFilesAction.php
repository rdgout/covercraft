<?php

namespace App\Actions;

use App\Contracts\GitHubServiceInterface;
use App\Models\Repository;
use App\Models\RepositoryFileCache;

class CacheRepositoryFilesAction
{
    public function __construct(private GitHubServiceInterface $githubService) {}

    /**
     * @return list<string>
     */
    public function execute(Repository $repository, string $branch, string $commitSha): array
    {
        $existing = RepositoryFileCache::query()
            ->where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->where('commit_sha', $commitSha)
            ->first();

        if ($existing) {
            return $existing->files;
        }

        $files = $this->githubService->fetchRepositoryFiles($repository, $commitSha);

        RepositoryFileCache::updateOrCreate(
            ['repository_id' => $repository->id, 'branch' => $branch],
            ['commit_sha' => $commitSha, 'files' => $files, 'cached_at' => now()],
        );

        return $files;
    }
}
