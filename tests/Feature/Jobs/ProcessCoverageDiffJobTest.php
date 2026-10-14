<?php

namespace Tests\Feature\Jobs;

use App\Actions\QueueChangedLineCoverageAction;
use App\Jobs\CalculateChangedLineCoverageJob;
use App\Jobs\ProcessCoverageDiffJob;
use App\Models\CoverageReport;
use App\Services\UnifiedDiffParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessCoverageDiffJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    private function report(string $content, array $metadata = []): CoverageReport
    {
        Storage::disk('local')->put('coverage/diffs/test.diff', $content);

        return CoverageReport::factory()->create([
            'diff_file_path' => 'coverage/diffs/test.diff',
            'diff_sha256' => hash('sha256', $content),
            'diff_status' => 'queued',
            'patch_coverage' => ['status' => 'pending'],
            'diff_metadata' => array_replace(['encoding' => 'plain', 'file_count' => 1, 'added_lines' => 2, 'deleted_lines' => 1], $metadata),
        ]);
    }

    private function diff(): string
    {
        return "diff --git a/src/Foo.php b/src/Foo.php\n--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -1 +1,2 @@\n-old\n+new\n+second\n";
    }

    private function process(CoverageReport $report): void
    {
        (new ProcessCoverageDiffJob($report->id, $report->diff_sha256))->handle(new UnifiedDiffParser, new QueueChangedLineCoverageAction);
    }

    public function test_indexes_ranges_and_schedules_calculation_after_clover(): void
    {
        $report = $this->report($this->diff());
        $this->process($report);
        $report->refresh();
        $this->assertSame('completed', $report->diff_status);
        $record = json_decode(Storage::disk('local')->get($report->diff_index_path), true);
        $this->assertSame([['start' => 1, 'end' => 2]], $record['added_ranges']);
        Queue::assertPushed(CalculateChangedLineCoverageJob::class);
        $this->process($report);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_diff_can_finish_before_clover_without_calculating(): void
    {
        $report = $this->report($this->diff());
        $report->update(['status' => 'pending']);
        $this->process($report);
        $this->assertSame('completed', $report->fresh()->diff_status);
        $this->assertSame('awaiting_coverage', $report->fresh()->patch_coverage['status']);
        Queue::assertNotPushed(CalculateChangedLineCoverageJob::class);
    }

    public function test_bad_counts_do_not_publish_partial_index_or_fail_clover(): void
    {
        $report = $this->report($this->diff(), ['file_count' => 2]);
        $this->process($report);
        $report->refresh();
        $this->assertSame('failed', $report->diff_status);
        $this->assertSame('count_mismatch', $report->diff_error_code);
        $this->assertSame('completed', $report->status);
        $this->assertNull($report->diff_index_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_malformed_diff_and_checksum_corruption_are_terminal(): void
    {
        $report = $this->report($this->diff().'+trailing\n');
        $this->process($report);
        $this->assertSame('invalid_diff', $report->fresh()->diff_error_code);
        $report = $this->report($this->diff());
        Storage::disk('local')->put($report->diff_file_path, 'changed');
        $this->process($report);
        $this->assertSame('checksum_mismatch', $report->fresh()->diff_error_code);
    }

    public function test_empty_gzip_diff_is_complete(): void
    {
        $report = $this->report(gzencode(''), ['encoding' => 'gzip', 'file_count' => 0, 'added_lines' => 0, 'deleted_lines' => 0]);
        $this->process($report);
        $this->assertSame('completed', $report->fresh()->diff_status);
        $this->assertSame('', Storage::disk('local')->get($report->fresh()->diff_index_path));
    }

    public function test_missing_report_and_obsolete_job_are_harmless(): void
    {
        (new ProcessCoverageDiffJob(999999, str_repeat('a', 64)))->handle(new UnifiedDiffParser, new QueueChangedLineCoverageAction);
        $report = $this->report($this->diff());
        (new ProcessCoverageDiffJob($report->id, str_repeat('a', 64)))->handle(new UnifiedDiffParser, new QueueChangedLineCoverageAction);
        $this->assertSame('queued', $report->fresh()->diff_status);
    }

    public function test_retry_exhaustion_preserves_clover_and_cannot_overwrite_completed_index(): void
    {
        $report = $this->report($this->diff());
        (new ProcessCoverageDiffJob($report->id, $report->diff_sha256))->failed(new \RuntimeException('private storage detail'));
        $this->assertSame('storage_failed', $report->fresh()->diff_error_code);
        $this->assertSame('completed', $report->fresh()->status);
        $report->refresh()->update(['diff_status' => 'queued']);
        $this->process($report);
        (new ProcessCoverageDiffJob($report->id, $report->diff_sha256))->failed(new \RuntimeException);
        $this->assertSame('completed', $report->fresh()->diff_status);
    }
}
