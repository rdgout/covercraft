<?php

namespace Tests\Feature\Console;

use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchRepositoryFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_files_with_explicit_branch_and_commit(): void
    {
        $repository = Repository::factory()->create();

        Http::fake([
            '*/repos/*/git/trees/*' => Http::response([
                'tree' => [
                    ['type' => 'blob', 'path' => 'src/File1.php'],
                    ['type' => 'blob', 'path' => 'src/File2.php'],
                    ['type' => 'tree', 'path' => 'src'],
                ],
            ]),
        ]);

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            'branch' => 'main',
            'commit' => str_repeat('a', 40),
        ])
            ->assertSuccessful()
            ->expectsOutput('Fetching files for '.$repository->owner.'/'.$repository->name.'...')
            ->expectsOutput('✓ Successfully cached 2 files');

        $this->assertDatabaseHas('repository_file_cache', [
            'repository_id' => $repository->id,
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
        ]);

        $cache = RepositoryFileCache::where('repository_id', $repository->id)->first();
        $this->assertCount(2, $cache->files);
        $this->assertContains('src/File1.php', $cache->files);
    }

    public function test_fetches_files_for_latest_coverage_report(): void
    {
        $repository = Repository::factory()->create();
        $report = CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'develop',
                'commit_sha' => str_repeat('b', 40),
                'archived' => false,
            ]);

        Http::fake([
            '*/repos/*/git/trees/*' => Http::response([
                'tree' => [
                    ['type' => 'blob', 'path' => 'tests/TestFile.php'],
                ],
            ]),
        ]);

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            '--latest' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('Using latest report: develop @ '.str_repeat('b', 40))
            ->expectsOutput('✓ Successfully cached 1 files');

        $this->assertDatabaseHas('repository_file_cache', [
            'repository_id' => $repository->id,
            'branch' => 'develop',
            'commit_sha' => str_repeat('b', 40),
        ]);
    }

    public function test_fails_when_repository_not_found(): void
    {
        $this->artisan('coverage:fetch-files', [
            'repository' => 999,
            '--latest' => true,
        ])
            ->assertFailed()
            ->expectsOutput('Repository with ID 999 not found.');
    }

    public function test_fails_when_no_coverage_reports_exist_for_latest(): void
    {
        $repository = Repository::factory()->create();

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            '--latest' => true,
        ])
            ->assertFailed()
            ->expectsOutput('No coverage reports found for this repository.');
    }

    public function test_fails_when_branch_or_commit_missing_without_latest_flag(): void
    {
        $repository = Repository::factory()->create();

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
        ])
            ->assertFailed()
            ->expectsOutput('Both branch and commit are required, or use --latest flag.');
    }

    public function test_handles_github_api_errors_gracefully(): void
    {
        $repository = Repository::factory()->create();

        Http::fake([
            '*/repos/*/git/trees/*' => Http::response([], 404),
        ]);

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            'branch' => 'main',
            'commit' => str_repeat('c', 40),
        ])
            ->assertFailed()
            ->expectsOutputToContain('Failed to fetch files:');
    }

    public function test_uses_cached_files_if_already_fetched(): void
    {
        $repository = Repository::factory()->create();

        RepositoryFileCache::create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'commit_sha' => str_repeat('d', 40),
            'files' => ['cached.php', 'file.php'],
            'cached_at' => now(),
        ]);

        Http::fake();

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            'branch' => 'main',
            'commit' => str_repeat('d', 40),
        ])
            ->assertSuccessful()
            ->expectsOutput('✓ Successfully cached 2 files');

        Http::assertNothingSent();
    }

    public function test_ignores_archived_reports_when_using_latest(): void
    {
        $repository = Repository::factory()->create();

        CoverageReport::factory()
            ->for($repository)
            ->archived()
            ->create(['branch' => 'old-branch']);

        $latestReport = CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'current-branch',
                'commit_sha' => str_repeat('e', 40),
                'archived' => false,
            ]);

        Http::fake([
            '*/repos/*/git/trees/*' => Http::response([
                'tree' => [
                    ['type' => 'blob', 'path' => 'current.php'],
                ],
            ]),
        ]);

        $this->artisan('coverage:fetch-files', [
            'repository' => $repository->id,
            '--latest' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('Using latest report: current-branch @ '.str_repeat('e', 40));

        $cache = RepositoryFileCache::where('repository_id', $repository->id)
            ->where('branch', 'current-branch')
            ->first();

        $this->assertNotNull($cache);
    }
}
