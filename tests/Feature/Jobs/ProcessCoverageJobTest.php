<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PostPullRequestCommentJob;
use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Services\CloverParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessCoverageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_successfully_parses_clover_xml(): void
    {
        Queue::fake();
        $report = $this->createReportWithCloverFile($this->validCloverXml());

        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->coverage_percentage);
        $this->assertNotNull($report->completed_at);
    }

    public function test_dispatches_post_pull_request_comment_job_after_processing(): void
    {
        Queue::fake();
        $report = $this->createReportWithCloverFile($this->validCloverXml());

        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        Queue::assertPushed(PostPullRequestCommentJob::class, function ($job) use ($report) {
            return $job->coverageReportId === $report->id;
        });
    }

    public function test_creates_correct_number_of_file_records(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="src/Bar.php">
      <line num="1" type="stmt" count="0"/>
    </file>
    <file name="src/Baz.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $report = $this->createReportWithCloverFile($xml);
        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        $this->assertCount(3, CoverageFile::where('coverage_report_id', $report->id)->get());
    }

    public function test_calculates_correct_coverage_percentage(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="1"/>
      <line num="3" type="stmt" count="0"/>
      <line num="4" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

        $report = $this->createReportWithCloverFile($xml);
        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        $report->refresh();
        $this->assertEquals('50.00', $report->coverage_percentage);
    }

    public function test_archives_previous_reports_on_same_branch(): void
    {
        $repository = Repository::factory()->create();

        $oldReport = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'archived' => false,
        ]);

        $newReport = $this->createReportWithCloverFile(
            $this->validCloverXml(),
            ['repository_id' => $repository->id, 'branch' => 'main'],
        );

        (new ProcessCoverageJob($newReport->id))->handle(new CloverParser);

        $this->assertTrue($oldReport->fresh()->archived);
        $this->assertFalse($newReport->fresh()->archived);
    }

    public function test_does_not_archive_reports_on_different_branch(): void
    {
        $repository = Repository::factory()->create();

        $mainReport = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'archived' => false,
        ]);

        $featureReport = $this->createReportWithCloverFile(
            $this->validCloverXml(),
            ['repository_id' => $repository->id, 'branch' => 'feature/login'],
        );

        (new ProcessCoverageJob($featureReport->id))->handle(new CloverParser);

        $this->assertFalse($mainReport->fresh()->archived);
    }

    public function test_stores_compressed_line_coverage_data(): void
    {
        $report = $this->createReportWithCloverFile($this->validCloverXml());
        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        $file = CoverageFile::where('coverage_report_id', $report->id)->first();
        $decompressed = json_decode(gzuncompress($file->line_coverage_data), true);

        $this->assertIsArray($decompressed);
        $this->assertNotEmpty($decompressed);
    }

    public function test_failed_method_marks_report_as_failed(): void
    {
        $report = CoverageReport::factory()->pending()->create();

        $job = new ProcessCoverageJob($report->id);
        $job->failed(new \RuntimeException('Parse error'));

        $report->refresh();
        $this->assertEquals('failed', $report->status);
        $this->assertEquals('Parse error', $report->error_message);
    }

    public function test_handles_missing_report_gracefully_in_failed(): void
    {
        $job = new ProcessCoverageJob(99999);
        $job->failed(new \RuntimeException('Not found'));

        $this->assertDatabaseMissing('coverage_reports', ['id' => 99999]);
    }

    private function createReportWithCloverFile(string $xml, array $overrides = []): CoverageReport
    {
        $filename = 'coverage/test_'.uniqid().'.xml';
        Storage::disk('local')->put($filename, $xml);

        return CoverageReport::factory()->pending()->create(array_merge(
            ['clover_file_path' => $filename],
            $overrides,
        ));
    }

    private function validCloverXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="0"/>
      <line num="3" type="stmt" count="5"/>
    </file>
  </project>
</coverage>
XML;
    }
}
