<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_valid_submission_returns_202(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'report_id', 'message'])
            ->assertJson(['status' => 'queued']);
    }

    public function test_missing_repository_returns_422(): void
    {
        $response = $this->postJson('/api/coverage', [
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['repository']);
    }

    public function test_missing_branch_returns_422(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch']);
    }

    public function test_missing_commit_sha_returns_422(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commit_sha']);
    }

    public function test_invalid_commit_sha_length_returns_422(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => 'too-short',
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commit_sha']);
    }

    public function test_missing_clover_file_returns_422(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clover_file']);
    }

    public function test_file_is_stored(): void
    {
        $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $files = Storage::disk('local')->files('coverage');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('acme_app_main_aaaaaaaa_', $files[0]);
    }

    public function test_job_is_dispatched(): void
    {
        $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        Queue::assertPushed(ProcessCoverageJob::class, function (ProcessCoverageJob $job) {
            return $job->coverageReportId === CoverageReport::first()->id;
        });
    }

    public function test_creates_repository_on_first_submission(): void
    {
        $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $this->assertDatabaseHas('repositories', [
            'owner' => 'acme',
            'name' => 'app',
        ]);
    }

    public function test_reuses_existing_repository(): void
    {
        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $this->assertDatabaseCount('repositories', 1);
        $this->assertEquals($repository->id, CoverageReport::first()->repository_id);
    }

    public function test_creates_pending_coverage_report(): void
    {
        $this->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'feature/login',
            'commit_sha' => str_repeat('b', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
        ]);

        $this->assertDatabaseHas('coverage_reports', [
            'branch' => 'feature/login',
            'commit_sha' => str_repeat('b', 40),
            'status' => 'pending',
        ]);
    }

    public function test_status_endpoint_returns_report_status(): void
    {
        $report = CoverageReport::factory()->create(['status' => 'completed']);

        $response = $this->getJson("/api/coverage/status/{$report->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'completed',
                'report_id' => $report->id,
            ]);
    }

    public function test_status_endpoint_includes_error_for_failed_reports(): void
    {
        $report = CoverageReport::factory()->failed()->create();

        $response = $this->getJson("/api/coverage/status/{$report->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'failed',
            ])
            ->assertJsonStructure(['error']);
    }

    public function test_status_endpoint_returns_404_for_missing_report(): void
    {
        $response = $this->getJson('/api/coverage/status/999');

        $response->assertNotFound();
    }
}
