<?php

namespace App\Services;

use App\Contracts\GitHubServiceInterface;
use App\Jobs\RefreshGitHubRepositoriesCacheJob;
use App\Models\Repository;
use Illuminate\Support\Facades\Cache;

class CachedGitHubService implements GitHubServiceInterface
{
    const REPOSITORY_CACHE_STALE_MINUTES = 15;

    public function __construct(private GitHubService $github) {}

    /**
     * @return list<array{full_name: string, owner: string, name: string, default_branch: string}>
     */
    public function listUserRepositories(): array
    {
        $key = $this->repositoryCacheKey();
        $freshKey = $key.'_fresh';

        $cached = Cache::get($key);

        if ($cached === null) {
            $repos = $this->github->listUserRepositories();
            Cache::put($key, $repos, now()->addDay());
            Cache::put($freshKey, true, now()->addMinutes(self::REPOSITORY_CACHE_STALE_MINUTES));

            return $repos;
        }

        if (! Cache::has($freshKey)) {
            RefreshGitHubRepositoriesCacheJob::dispatch();
        }

        return $cached;
    }

    public function refreshRepositoriesCache(): void
    {
        $key = $this->repositoryCacheKey();
        $freshKey = $key.'_fresh';

        $repos = $this->github->listUserRepositories();

        Cache::put($key, $repos, now()->addDay());
        Cache::put($freshKey, true, now()->addMinutes(self::REPOSITORY_CACHE_STALE_MINUTES));
    }

    /**
     * @return list<string>
     */
    public function listBranches(string $owner, string $name): array
    {
        return $this->github->listBranches($owner, $name);
    }

    /**
     * @return list<string>
     */
    public function fetchRepositoryFiles(Repository $repository, string $commitSha): array
    {
        return $this->github->fetchRepositoryFiles($repository, $commitSha);
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        return $this->github->verifyWebhookSignature($payload, $signature, $secret);
    }

    public function fetchFileContents(Repository $repository, string $commitSha, string $filePath): string
    {
        return $this->github->fetchFileContents($repository, $commitSha, $filePath);
    }

    private function repositoryCacheKey(): string
    {
        return 'github_user_repos_'.md5(config('coverage.github_token', ''));
    }
}
