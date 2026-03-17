<?php

namespace Tests\Feature\Jobs;

use App\Contracts\GitHubServiceInterface;
use App\Jobs\FetchRepositoryFilesJob;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchRepositoryFilesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_and_caches_repository_files(): void
    {
        $repository = Repository::factory()->create();

        Http::fake([
            '*/repos/*/git/trees/*' => Http::response([
                'tree' => [
                    ['type' => 'blob', 'path' => 'src/Foo.php'],
                    ['type' => 'blob', 'path' => 'src/Bar.php'],
                    ['type' => 'tree', 'path' => 'src'],
                ],
            ]),
        ]);

        (new FetchRepositoryFilesJob($repository->id, 'main', str_repeat('a', 40)))->handle(
            app(GitHubServiceInterface::class)
        );

        $this->assertDatabaseHas('repository_file_cache', [
            'repository_id' => $repository->id,
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
        ]);

        $cache = RepositoryFileCache::where('repository_id', $repository->id)->first();
        $this->assertCount(2, $cache->files);
        $this->assertContains('src/Foo.php', $cache->files);
        $this->assertContains('src/Bar.php', $cache->files);
    }

    public function test_uses_existing_cache_when_commit_matches(): void
    {
        $repository = Repository::factory()->create();
        $commitSha = str_repeat('b', 40);

        RepositoryFileCache::create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'commit_sha' => $commitSha,
            'files' => ['cached/File.php'],
            'cached_at' => now(),
        ]);

        Http::fake();

        (new FetchRepositoryFilesJob($repository->id, 'main', $commitSha))->handle(
            app(GitHubServiceInterface::class)
        );

        Http::assertNothingSent();
    }

    public function test_fails_when_repository_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new FetchRepositoryFilesJob(99999, 'main', str_repeat('a', 40)))->handle(
            app(GitHubServiceInterface::class)
        );
    }
}
