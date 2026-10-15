<?php

namespace Tests\Feature\Services;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Services\PatchCoverageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatchCoverageCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private CoverageReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->report = CoverageReport::factory()->archived()->create([
            'diff_status' => 'completed', 'diff_index_path' => 'coverage/index.jsonl',
            'diff_sha256' => str_repeat('a', 64), 'diff_base_sha' => str_repeat('b', 40),
            'diff_metadata' => ['scope' => ['include_patterns' => ['*.php'], 'exclude_patterns' => ['tests/*']]],
        ]);
    }

    private function index(array $records): void
    {
        Storage::disk('local')->put($this->report->diff_index_path, implode('', array_map(fn ($record): string => json_encode(array_replace([
            'path' => 'src/Foo.php', 'previous_path' => null, 'kind' => 'modified', 'added_ranges' => [['start' => 2, 'end' => 5]], 'additions' => 4, 'deletions' => 0,
        ], $record))."\n", $records)));
    }

    private function file(string $path, array $lines): CoverageFile
    {
        return CoverageFile::factory()->for($this->report)->create(['file_path' => $path, 'line_coverage_data' => base64_encode(gzcompress(json_encode($lines)))]);
    }

    private function calculate(): array
    {
        return (new PatchCoverageCalculator)->calculate($this->report);
    }

    public function test_intersects_executable_lines_at_uploaded_commit_without_github(): void
    {
        $this->index([[]]);
        $file = $this->file('src/Foo.php', [1 => ['count' => 0, 'covered' => false], 2 => ['count' => 3, 'covered' => true], 3 => ['count' => 0, 'covered' => false], 5 => ['count' => 1, 'covered' => true]]);
        $result = $this->calculate();
        $this->assertSame(66.67, $result['percentage']);
        $this->assertSame(2, $result['covered_lines']);
        $this->assertSame(3, $result['total_lines']);
        $this->assertSame($this->report->commit_sha, $result['head_sha']);
        $detail = (new PatchCoverageCalculator)->fileCoverage($this->report, $file);
        $this->assertSame([['start' => 3, 'end' => 3]], $detail['uncovered_ranges']);
        Http::assertNothingSent();
    }

    public function test_missing_source_data_suppresses_overall_number_but_excluded_files_do_not(): void
    {
        $this->index([[], ['path' => 'src/Missing.php'], ['path' => 'tests/Test.php'], ['path' => 'image.png']]);
        $this->file('src/Foo.php', [2 => ['count' => 1, 'covered' => true]]);
        $result = $this->calculate();
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['percentage']);
        $this->assertSame(2, $result['file_count']);
        $this->assertSame('missing_coverage_file', $result['files'][1]['reason']);
    }

    public function test_zero_coverage_is_distinct_from_no_executable_changes(): void
    {
        $this->index([[]]);
        $file = $this->file('src/Foo.php', [2 => ['count' => 0, 'covered' => false]]);
        $this->assertSame(0.0, $this->calculate()['percentage']);
        $file->update(['line_coverage_data' => base64_encode(gzcompress(json_encode([1 => ['count' => 1, 'covered' => true]])))]);
        $result = $this->calculate();
        $this->assertSame('no_executable_changes', $result['status']);
        $this->assertNull($result['percentage']);
        $this->index([]);
        $this->assertSame('no_executable_changes', $this->calculate()['status']);
    }

    public function test_renames_weighted_counts_deleted_files_and_binary_source(): void
    {
        $this->index([['previous_path' => 'src/Old.php'], ['path' => 'src/Bar.php'], ['path' => 'src/Deleted.php', 'kind' => 'deleted']]);
        $this->file('src/Foo.php', [2 => ['count' => 1, 'covered' => true]]);
        $this->file('src/Bar.php', [2 => ['count' => 0, 'covered' => false], 3 => ['count' => 0, 'covered' => false], 4 => ['count' => 0, 'covered' => false]]);
        $result = $this->calculate();
        $this->assertSame(25.0, $result['percentage']);
        $this->assertSame('src/Old.php', $result['files'][0]['previous_path']);
        $this->index([['kind' => 'binary', 'additions' => 0]]);
        $this->assertSame('binary_source', $this->calculate()['files'][0]['reason']);
    }

    public function test_invalid_compressed_coverage_is_incomplete(): void
    {
        $this->index([[]]);
        CoverageFile::factory()->for($this->report)->create(['file_path' => 'src/Foo.php', 'line_coverage_data' => 'invalid']);
        $this->assertSame('invalid_coverage', $this->calculate()['files'][0]['reason']);
    }

    public function test_many_files_use_bounded_batches_and_summary_without_losing_totals(): void
    {
        config(['coverage.diff.batch_size' => 10]);
        $records = [];
        for ($number = 0; $number < 125; $number++) {
            $path = 'src/F'.$number.'.php';
            $records[] = ['path' => $path];
            $this->file($path, [2 => ['count' => 1, 'covered' => true]]);
        }
        $this->index($records);
        $connection = $this->report->getConnection();
        $connection->enableQueryLog();
        $result = $this->calculate();
        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();
        $this->assertCount(13, $queries);
        $this->assertSame(125, $result['covered_lines']);
        $this->assertSame(125, $result['file_count']);
        $this->assertCount(100, $result['files']);
        $this->assertTrue($result['files_truncated']);
    }

    public function test_coverage_map_limit_produces_incomplete_instead_of_exhausting_memory(): void
    {
        $this->index([[]]);
        $this->file('src/Foo.php', [2 => ['count' => 1, 'covered' => true]]);
        config(['coverage.diff.max_coverage_file_bytes' => 8]);
        $result = $this->calculate();
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['percentage']);
    }
}
