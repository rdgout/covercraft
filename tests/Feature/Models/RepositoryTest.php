<?php

namespace Tests\Feature\Models;

use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_repository_via_factory(): void
    {
        $repository = Repository::factory()->create();

        $this->assertDatabaseHas('repositories', [
            'id' => $repository->id,
            'owner' => $repository->owner,
            'name' => $repository->name,
        ]);
    }

    public function test_default_branch_defaults_to_main(): void
    {
        $repository = Repository::factory()->create();

        $this->assertEquals('main', $repository->default_branch);
    }

    public function test_with_default_branch_state(): void
    {
        $repository = Repository::factory()->withDefaultBranch('develop')->create();

        $this->assertEquals('develop', $repository->default_branch);
    }

    public function test_webhook_secret_is_hidden(): void
    {
        $repository = Repository::factory()->withWebhookSecret()->create();

        $this->assertArrayNotHasKey('webhook_secret', $repository->toArray());
        $this->assertNotNull($repository->webhook_secret);
    }

    public function test_has_many_coverage_reports(): void
    {
        $repository = Repository::factory()->create();
        CoverageReport::factory()->count(3)->create(['repository_id' => $repository->id]);

        $this->assertCount(3, $repository->coverageReports);
    }

    public function test_latest_coverage_report_returns_non_archived(): void
    {
        $repository = Repository::factory()->create();

        CoverageReport::factory()->archived()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
        ]);

        $current = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'archived' => false,
        ]);

        $latest = $repository->latestCoverageReport;

        $this->assertNotNull($latest);
        $this->assertEquals($current->id, $latest->id);
    }

    public function test_latest_coverage_report_only_returns_default_branch(): void
    {
        $repository = Repository::factory()->create(['default_branch' => 'main']);

        CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'feat/other-branch',
            'archived' => false,
        ]);

        $defaultBranchReport = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'archived' => false,
        ]);

        $latest = $repository->latestCoverageReport;

        $this->assertNotNull($latest);
        $this->assertEquals($defaultBranchReport->id, $latest->id);
    }

    public function test_has_many_file_cache(): void
    {
        $repository = Repository::factory()->create();
        RepositoryFileCache::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
        ]);
        RepositoryFileCache::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'develop',
        ]);

        $this->assertCount(2, $repository->fileCache);
    }

    public function test_cascade_deletes_coverage_reports(): void
    {
        $repository = Repository::factory()->create();
        CoverageReport::factory()->count(2)->create(['repository_id' => $repository->id]);

        $repository->delete();

        $this->assertDatabaseCount('coverage_reports', 0);
    }

    public function test_cascade_deletes_file_cache(): void
    {
        $repository = Repository::factory()->create();
        RepositoryFileCache::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
        ]);

        $repository->delete();

        $this->assertDatabaseCount('repository_file_cache', 0);
    }

    public function test_owner_name_unique_constraint(): void
    {
        Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        $this->expectException(UniqueConstraintViolationException::class);

        Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
    }
}
