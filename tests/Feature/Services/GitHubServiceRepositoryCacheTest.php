<?php

namespace Tests\Feature\Services;

use App\Jobs\RefreshGitHubRepositoriesCacheJob;
use App\Services\CachedGitHubService;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceRepositoryCacheTest extends TestCase
{
    use RefreshDatabase;

    private CachedGitHubService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CachedGitHubService(new GitHubService);
        Cache::flush();
    }

    public function test_list_user_repositories_fetches_and_caches_on_miss(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response([
                ['full_name' => 'acme/app', 'name' => 'app', 'owner' => ['login' => 'acme'], 'default_branch' => 'main'],
            ]),
        ]);

        $repos = $this->service->listUserRepositories();

        $this->assertCount(1, $repos);
        $this->assertEquals('acme/app', $repos[0]['full_name']);
        Http::assertSentCount(1);

        $key = 'github_user_repos_'.md5(config('coverage.github_token', ''));
        $this->assertNotNull(Cache::get($key));
        $this->assertTrue(Cache::has($key.'_fresh'));
    }

    public function test_list_user_repositories_returns_cached_data_when_fresh(): void
    {
        $key = 'github_user_repos_'.md5(config('coverage.github_token', ''));
        $cachedRepos = [['full_name' => 'acme/cached', 'name' => 'cached', 'owner' => 'acme', 'default_branch' => 'main']];

        Cache::put($key, $cachedRepos, now()->addDay());
        Cache::put($key.'_fresh', true, now()->addMinutes(CachedGitHubService::REPOSITORY_CACHE_STALE_MINUTES));

        Http::fake();

        $repos = $this->service->listUserRepositories();

        $this->assertEquals($cachedRepos, $repos);
        Http::assertNothingSent();
    }

    public function test_list_user_repositories_returns_stale_data_and_dispatches_job(): void
    {
        Bus::fake();

        $key = 'github_user_repos_'.md5(config('coverage.github_token', ''));
        $staleRepos = [['full_name' => 'acme/stale', 'name' => 'stale', 'owner' => 'acme', 'default_branch' => 'main']];

        Cache::put($key, $staleRepos, now()->addDay());
        // No fresh key — simulates stale cache

        Http::fake();

        $repos = $this->service->listUserRepositories();

        $this->assertEquals($staleRepos, $repos);
        Http::assertNothingSent();
        Bus::assertDispatched(RefreshGitHubRepositoriesCacheJob::class);
    }

    public function test_list_user_repositories_does_not_dispatch_job_when_fresh(): void
    {
        Bus::fake();

        $key = 'github_user_repos_'.md5(config('coverage.github_token', ''));
        $repos = [['full_name' => 'acme/app', 'name' => 'app', 'owner' => 'acme', 'default_branch' => 'main']];

        Cache::put($key, $repos, now()->addDay());
        Cache::put($key.'_fresh', true, now()->addMinutes(CachedGitHubService::REPOSITORY_CACHE_STALE_MINUTES));

        Http::fake();

        $this->service->listUserRepositories();

        Bus::assertNotDispatched(RefreshGitHubRepositoriesCacheJob::class);
    }

    public function test_refresh_repositories_cache_updates_cache(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response([
                ['full_name' => 'acme/refreshed', 'name' => 'refreshed', 'owner' => ['login' => 'acme'], 'default_branch' => 'main'],
            ]),
        ]);

        $this->service->refreshRepositoriesCache();

        $key = 'github_user_repos_'.md5(config('coverage.github_token', ''));
        $cached = Cache::get($key);

        $this->assertNotNull($cached);
        $this->assertEquals('acme/refreshed', $cached[0]['full_name']);
        $this->assertTrue(Cache::has($key.'_fresh'));
    }
}
