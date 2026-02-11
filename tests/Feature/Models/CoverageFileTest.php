<?php

namespace Tests\Feature\Models;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_via_factory(): void
    {
        $file = CoverageFile::factory()->create();

        $this->assertDatabaseHas('coverage_files', ['id' => $file->id]);
    }

    public function test_belongs_to_coverage_report(): void
    {
        $report = CoverageReport::factory()->create();
        $file = CoverageFile::factory()->create(['coverage_report_id' => $report->id]);

        $this->assertEquals($report->id, $file->coverageReport->id);
    }

    public function test_line_coverage_accessor_round_trip(): void
    {
        $originalData = [
            1 => ['covered' => true, 'count' => 5],
            2 => ['covered' => false, 'count' => 0],
            3 => ['covered' => true, 'count' => 12],
        ];

        $file = CoverageFile::factory()->create([
            'line_coverage_data' => gzcompress(json_encode($originalData)),
        ]);

        $file->refresh();
        $decompressed = $file->line_coverage;

        $this->assertEquals($originalData, $decompressed);
    }

    public function test_line_coverage_accessor_with_empty_data(): void
    {
        $file = CoverageFile::factory()->create([
            'line_coverage_data' => gzcompress(json_encode([])),
        ]);

        $file->refresh();

        $this->assertEquals([], $file->line_coverage);
    }

    public function test_line_coverage_accessor_with_large_dataset(): void
    {
        $data = [];
        for ($i = 1; $i <= 1000; $i++) {
            $data[$i] = [
                'covered' => $i % 2 === 0,
                'count' => $i % 2 === 0 ? rand(1, 100) : 0,
            ];
        }

        $file = CoverageFile::factory()->create([
            'line_coverage_data' => gzcompress(json_encode($data)),
        ]);

        $file->refresh();

        $this->assertEquals($data, $file->line_coverage);
        $this->assertCount(1000, $file->line_coverage);
    }

    public function test_coverage_percentage_cast(): void
    {
        $file = CoverageFile::factory()->create([
            'coverage_percentage' => 75.55,
        ]);

        $file->refresh();

        $this->assertEquals('75.55', $file->coverage_percentage);
    }
}
