<?php

namespace Tests\Feature\ErrorHandling;

use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TeamAccessToken;
use App\Models\User;
use App\Services\CloverParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Team $team;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->withTeams(1)->create();
        $this->team = $this->user->teams->first();
        $this->actingAs($this->user);

        $plainToken = Str::random(64);
        TeamAccessToken::factory()->forTeam($this->team)->create([
            'token' => hash('sha256', $plainToken),
        ]);
        $this->apiToken = $plainToken;
    }

    public function test_job_marks_report_as_failed_on_invalid_xml(): void
    {
        Storage::disk('local')->put('coverage/bad.xml', 'not xml at all <<<');

        $report = CoverageReport::factory()->pending()->create([
            'clover_file_path' => 'coverage/bad.xml',
        ]);

        $job = new ProcessCoverageJob($report->id);

        try {
            $job->handle(new CloverParser);
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $report->refresh();
        $this->assertEquals('failed', $report->status);
        $this->assertNotNull($report->error_message);
    }

    public function test_job_marks_report_as_failed_on_missing_file(): void
    {
        $report = CoverageReport::factory()->pending()->create([
            'clover_file_path' => 'coverage/nonexistent.xml',
        ]);

        $job = new ProcessCoverageJob($report->id);

        try {
            $job->handle(new CloverParser);
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $report->refresh();
        $this->assertEquals('failed', $report->status);
    }

    public function test_status_api_shows_error_for_failed_report(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        $report = CoverageReport::factory()->failed()->create([
            'repository_id' => $repo->id,
            'error_message' => 'Parse error: invalid XML',
        ]);

        $response = $this->withToken($this->apiToken)->getJson("/api/coverage/status/{$report->id}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'failed',
            'error' => 'Parse error: invalid XML',
        ]);
    }

    public function test_dashboard_handles_repository_with_failed_reports(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        $report = CoverageReport::factory()->failed()->create([
            'repository_id' => $repo->id,
            'error_message' => 'Parse failed',
        ]);

        $response = $this->get("/dashboard/{$report->repository_id}");

        $response->assertOk();
        $response->assertSee('failed');
    }

    public function test_api_handles_empty_commit_sha(): void
    {
        $response = $this->withToken($this->apiToken)->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['commit_sha']);
    }

    public function test_branch_page_without_cache_shows_empty_tree(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
        ]);

        $response = $this->get("/dashboard/{$repo->id}/main");

        $response->assertOk();
        $response->assertSee('No file tree data available');
    }
}
