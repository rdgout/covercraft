<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessCoverageDiffJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TeamAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoverageDiffApiTest extends TestCase
{
    use RefreshDatabase;

    private CoverageReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        $team = Team::factory()->create();
        TeamAccessToken::factory()->forTeam($team)->create(['token' => hash('sha256', 'diff-token')]);
        $this->withToken('diff-token');
        $this->report = CoverageReport::factory()->for(Repository::factory()->forTeam($team))->create();
    }

    private function payload(string $content = '', array $overrides = []): array
    {
        return array_replace([
            'diff_file' => UploadedFile::fake()->createWithContent('changes.diff', $content),
            'base_commit_sha' => str_repeat('a', 40),
            'head_commit_sha' => $this->report->commit_sha,
            'sha256' => hash('sha256', $content),
            'file_count' => 0,
            'added_lines' => 0,
            'deleted_lines' => 0,
            'comparison_type' => 'merge_base',
            'base_branch' => 'main',
        ], $overrides);
    }

    private function endpoint(): string
    {
        return '/api/coverage/'.$this->report->id.'/diff';
    }

    public function test_accepts_empty_plain_diff_and_dispatches_one_job(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertAccepted()->assertJsonPath('report_id', $this->report->id);
        $fresh = $this->report->fresh();
        $this->assertSame('queued', $fresh->diff_status);
        $this->assertSame('plain', $fresh->diff_metadata['encoding']);
        $this->assertSame('completed', $fresh->status);
        Storage::disk('local')->assertExists($fresh->diff_file_path);
        Queue::assertPushed(ProcessCoverageDiffJob::class, fn ($job): bool => $job->coverageReportId === $fresh->id && $job->artifactSha256 === $fresh->diff_sha256);
    }

    public function test_accepts_gzip_and_archived_reports(): void
    {
        $this->report->update(['archived' => true]);
        $this->postJson($this->endpoint(), $this->payload(gzencode('')))->assertAccepted();
        $this->assertSame('gzip', $this->report->fresh()->diff_metadata['encoding']);
    }

    public function test_identical_upload_is_idempotent_and_conflicting_upload_is_rejected(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertAccepted();
        $path = $this->report->fresh()->diff_file_path;
        $this->postJson($this->endpoint(), $this->payload())->assertAccepted();
        Queue::assertPushed(ProcessCoverageDiffJob::class, 1);
        $this->postJson($this->endpoint(), $this->payload('different'))->assertConflict();
        $this->postJson($this->endpoint(), $this->payload('', ['base_branch' => 'develop']))->assertConflict();
        $this->assertSame($path, $this->report->fresh()->diff_file_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_wrong_team_cannot_attach_a_diff(): void
    {
        $this->report = CoverageReport::factory()->create();
        $this->postJson($this->endpoint(), $this->payload())->assertForbidden();
        Queue::assertNotPushed(ProcessCoverageDiffJob::class);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_missing_report_is_not_found(): void
    {
        $this->postJson('/api/coverage/999999/diff', $this->payload())->assertNotFound();
    }

    #[DataProvider('invalidMetadata')]
    public function test_rejects_invalid_metadata(string $field, mixed $value): void
    {
        $this->postJson($this->endpoint(), $this->payload('', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        Queue::assertNotPushed(ProcessCoverageDiffJob::class);
    }

    public static function invalidMetadata(): array
    {
        return [
            ['base_commit_sha', 'not-a-sha'],
            ['head_commit_sha', str_repeat('b', 40)],
            ['sha256', 'invalid'],
            ['file_count', -1],
            ['file_count', 100001],
            ['added_lines', -1],
            ['deleted_lines', -1],
            ['comparison_type', 'unknown'],
            ['diff_file', null],
        ];
    }

    public function test_rejects_checksum_mismatch_without_storage(): void
    {
        $this->postJson($this->endpoint(), $this->payload('content', ['sha256' => str_repeat('a', 64)]))->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_enforces_upload_size_limit(): void
    {
        config(['coverage.diff.max_upload_kib' => 1]);
        $this->postJson($this->endpoint(), $this->payload(str_repeat('x', 1025)))->assertUnprocessable()->assertJsonValidationErrors('diff_file');
    }

    public function test_storage_failure_does_not_accept_artifact(): void
    {
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->andReturn(false);
        $this->postJson($this->endpoint(), $this->payload())->assertStatus(500);
        $this->assertNull($this->report->fresh()->diff_sha256);
        Queue::assertNotPushed(ProcessCoverageDiffJob::class);
    }
}
