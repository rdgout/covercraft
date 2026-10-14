<?php

namespace Tests\Feature\Jobs;

use App\Actions\QueueChangedLineCoverageAction;
use App\Jobs\CalculateChangedLineCoverageJob;
use App\Jobs\PostPullRequestCommentJob;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Services\PatchCoverageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CalculateChangedLineCoverageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_archived_report_offline_and_duplicate_job_is_idempotent(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        $report = CoverageReport::factory()->archived()->create([
            'diff_status' => 'completed', 'diff_index_path' => 'coverage/index.jsonl',
            'diff_sha256' => str_repeat('a', 64), 'diff_base_sha' => str_repeat('b', 40),
            'diff_metadata' => ['scope' => ['include_patterns' => ['*.php'], 'exclude_patterns' => []]],
            'patch_coverage' => ['status' => 'pending'],
        ]);
        Storage::disk('local')->put($report->diff_index_path, json_encode(['path' => 'src/Foo.php', 'previous_path' => null, 'kind' => 'modified', 'additions' => 1, 'added_ranges' => [['start' => 2, 'end' => 2]]])."\n");
        CoverageFile::factory()->for($report)->create(['file_path' => 'src/Foo.php', 'line_coverage_data' => base64_encode(gzcompress(json_encode([2 => ['count' => 1, 'covered' => true]])))]);
        $job = new CalculateChangedLineCoverageJob($report->id);
        $job->handle(new PatchCoverageCalculator, new QueueChangedLineCoverageAction);
        $this->assertSame(100, $report->fresh()->patch_coverage['percentage']);
        $this->assertTrue($report->fresh()->archived);
        $job->handle(new PatchCoverageCalculator, new QueueChangedLineCoverageAction);
        Queue::assertPushed(PostPullRequestCommentJob::class, 1);
        Http::assertNothingSent();
        $job->failed(new \RuntimeException);
        $this->assertSame('completed', $report->fresh()->patch_coverage['status']);
    }

    public function test_missing_report_and_pending_input_do_not_publish(): void
    {
        Queue::fake();
        (new CalculateChangedLineCoverageJob(9999))->handle(new PatchCoverageCalculator, new QueueChangedLineCoverageAction);
        $report = CoverageReport::factory()->pending()->create(['diff_status' => 'completed', 'diff_sha256' => str_repeat('a', 64)]);
        (new CalculateChangedLineCoverageJob($report->id))->handle(new PatchCoverageCalculator, new QueueChangedLineCoverageAction);
        $this->assertSame('awaiting_coverage', $report->fresh()->patch_coverage['status']);
        Queue::assertNothingPushed();
    }
}
