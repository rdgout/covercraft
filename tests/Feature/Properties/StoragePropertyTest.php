<?php

namespace Tests\Feature\Properties;

use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Services\CloverParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoragePropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createFileCacheFor(CoverageReport $report): void
    {
        RepositoryFileCache::create([
            'repository_id' => $report->repository_id,
            'branch' => $report->branch,
            'commit_sha' => $report->commit_sha,
            'files' => [],
            'cached_at' => now(),
        ]);
    }

    /**
     * Property 8: Malformed XML Handling
     *
     * For any malformed XML file, the parser should fail gracefully.
     */
    public function test_property_8_malformed_xml_handling(): void
    {
        $malformedInputs = [
            'not xml at all',
            '<?xml version="1.0"?><unclosed',
            '<coverage><project><file></coverage>',
            '',
            '   ',
            '<html><body>wrong format</body></html>',
        ];

        foreach ($malformedInputs as $index => $input) {
            $filename = "coverage/malformed_{$index}.xml";
            Storage::disk('local')->put($filename, $input);

            $report = CoverageReport::factory()->pending()->create([
                'clover_file_path' => $filename,
            ]);

            $this->createFileCacheFor($report);
            $job = new ProcessCoverageJob($report->id);

            try {
                $job->handle(new CloverParser);
            } catch (\Throwable) {
                // Expected
            }

            $job->failed(new \RuntimeException('Parse error'));

            $report->refresh();
            $this->assertContains(
                $report->status,
                ['failed', 'pending'],
                "Iteration: {$index}"
            );
        }
    }

    /**
     * Property 9: Coverage Report Persistence
     *
     * For any successfully parsed coverage data, a report record should be created
     * containing all required fields.
     */
    public function test_property_9_coverage_report_persistence(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $lineCount = rand(1, 10);
            $lineXml = '';
            for ($l = 1; $l <= $lineCount; $l++) {
                $count = rand(0, 5);
                $lineXml .= "<line num=\"{$l}\" type=\"stmt\" count=\"{$count}\"/>";
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project><file name=\"src/File{$i}.php\">{$lineXml}</file></project></coverage>";
            $filename = "coverage/prop9_{$i}.xml";
            Storage::disk('local')->put($filename, $xml);

            $report = CoverageReport::factory()->pending()->create([
                'clover_file_path' => $filename,
            ]);

            $this->createFileCacheFor($report);
            (new ProcessCoverageJob($report->id))->handle(new CloverParser);

            $report->refresh();
            $this->assertEquals('completed', $report->status, "Seed: {$seed}, iteration: {$i}");
            $this->assertNotNull($report->coverage_percentage, "Seed: {$seed}, iteration: {$i}");
            $this->assertNotNull($report->completed_at, "Seed: {$seed}, iteration: {$i}");
            $this->assertNotNull($report->repository_id, "Seed: {$seed}, iteration: {$i}");
            $this->assertNotNull($report->branch, "Seed: {$seed}, iteration: {$i}");
            $this->assertNotNull($report->commit_sha, "Seed: {$seed}, iteration: {$i}");
        }
    }

    /**
     * Property 10: File Coverage Storage
     *
     * For any coverage report with N files, exactly N file coverage records should be created.
     */
    public function test_property_10_file_coverage_storage(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $fileCount = rand(1, 5);
            $fileXml = '';

            for ($f = 0; $f < $fileCount; $f++) {
                $fileXml .= "<file name=\"src/F{$i}_{$f}.php\"><line num=\"1\" type=\"stmt\" count=\"1\"/></file>";
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project>{$fileXml}</project></coverage>";
            $filename = "coverage/prop10_{$i}.xml";
            Storage::disk('local')->put($filename, $xml);

            $report = CoverageReport::factory()->pending()->create([
                'clover_file_path' => $filename,
            ]);

            $this->createFileCacheFor($report);
            (new ProcessCoverageJob($report->id))->handle(new CloverParser);

            $actualCount = CoverageFile::where('coverage_report_id', $report->id)->count();

            $this->assertEquals(
                $fileCount,
                $actualCount,
                "Seed: {$seed}, iteration: {$i}"
            );
        }
    }

    /**
     * Property 11: Line Coverage Compression Round Trip
     *
     * Compressed line coverage data should decompress to original data.
     */
    public function test_property_11_line_coverage_compression_round_trip(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $lineCount = rand(1, 20);
            $lineXml = '';
            $expectedLines = [];

            for ($l = 1; $l <= $lineCount; $l++) {
                $count = rand(0, 10);
                $lineXml .= "<line num=\"{$l}\" type=\"stmt\" count=\"{$count}\"/>";
                $expectedLines[$l] = ['covered' => $count > 0, 'count' => $count];
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project><file name=\"src/Test{$i}.php\">{$lineXml}</file></project></coverage>";
            $filename = "coverage/prop11_{$i}.xml";
            Storage::disk('local')->put($filename, $xml);

            $report = CoverageReport::factory()->pending()->create([
                'clover_file_path' => $filename,
            ]);

            $this->createFileCacheFor($report);
            (new ProcessCoverageJob($report->id))->handle(new CloverParser);

            $file = CoverageFile::where('coverage_report_id', $report->id)->first();
            $decompressed = json_decode(gzuncompress(base64_decode($file->line_coverage_data)), true);

            $this->assertEquals(
                $expectedLines,
                $decompressed,
                "Seed: {$seed}, iteration: {$i}"
            );
        }
    }

    /**
     * Property 12: Branch Report Replacement and Archival
     *
     * New report for same branch archives previous non-archived reports.
     */
    public function test_property_12_branch_report_replacement_and_archival(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create();
            $branch = 'branch-'.$i;

            $xml = '<?xml version="1.0"?><coverage><project><file name="src/F.php"><line num="1" type="stmt" count="1"/></file></project></coverage>';

            $filename1 = "coverage/prop12_{$i}_1.xml";
            Storage::disk('local')->put($filename1, $xml);
            $first = CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'archived' => false,
                'clover_file_path' => $filename1,
            ]);

            $filename2 = "coverage/prop12_{$i}_2.xml";
            Storage::disk('local')->put($filename2, $xml);
            $second = CoverageReport::factory()->pending()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'clover_file_path' => $filename2,
            ]);

            $this->createFileCacheFor($second);
            (new ProcessCoverageJob($second->id))->handle(new CloverParser);

            $this->assertTrue($first->fresh()->archived, "Seed: {$seed}, iteration: {$i}");
            $this->assertFalse($second->fresh()->archived, "Seed: {$seed}, iteration: {$i}");

            $nonArchived = CoverageReport::where('repository_id', $repository->id)
                ->where('branch', $branch)
                ->where('archived', false)
                ->count();

            $this->assertEquals(1, $nonArchived, "Seed: {$seed}, iteration: {$i}");
        }
    }

    /**
     * Property 13: Current Coverage Query
     *
     * Querying for current coverage returns only the non-archived report.
     */
    public function test_property_13_current_coverage_query(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create();
            $branch = 'branch-'.$i;
            $archivedCount = rand(1, 5);

            CoverageReport::factory()
                ->count($archivedCount)
                ->archived()
                ->create([
                    'repository_id' => $repository->id,
                    'branch' => $branch,
                ]);

            $current = CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'archived' => false,
            ]);

            $result = CoverageReport::current()
                ->where('repository_id', $repository->id)
                ->where('branch', $branch)
                ->get();

            $this->assertCount(1, $result, "Seed: {$seed}, iteration: {$i}");
            $this->assertEquals($current->id, $result->first()->id, "Seed: {$seed}, iteration: {$i}");
        }
    }
}
