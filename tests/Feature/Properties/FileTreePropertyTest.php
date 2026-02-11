<?php

namespace Tests\Feature\Properties;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Services\FileTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileTreePropertyTest extends TestCase
{
    use RefreshDatabase;

    private FileTreeBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FileTreeBuilder;
    }

    /**
     * Property 17: File Coverage Classification
     *
     * Files with coverage > 0% should be "covered", files with 0% should be "uncovered".
     */
    public function test_property_17_file_coverage_classification(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $report = CoverageReport::factory()->create();
            $percentage = fake()->randomFloat(2, 0, 100);

            CoverageFile::factory()->create([
                'coverage_report_id' => $report->id,
                'file_path' => 'src/File.php',
                'coverage_percentage' => $percentage,
            ]);

            $tree = $this->builder->build($report, ['src/File.php']);
            $node = $tree['src']['children']['File.php'];

            $this->assertTrue($node['covered'], "Seed: {$seed}, iteration: {$i}");
            $this->assertEquals(
                $percentage,
                $node['coverage'],
                "Seed: {$seed}, iteration: {$i}"
            );
        }
    }

    /**
     * Property 18: Uncovered File Identification
     *
     * Files in repo but not in coverage report should get 0% coverage.
     */
    public function test_property_18_uncovered_file_identification(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $report = CoverageReport::factory()->create();
            $uncoveredCount = rand(1, 10);
            $files = [];

            for ($f = 0; $f < $uncoveredCount; $f++) {
                $files[] = 'src/Uncovered_'.$f.'.php';
            }

            $tree = $this->builder->build($report, $files);

            foreach ($files as $file) {
                $parts = explode('/', $file);
                $node = $tree;
                for ($p = 0; $p < count($parts) - 1; $p++) {
                    $node = $node[$parts[$p]]['children'];
                }
                $leaf = $node[end($parts)];

                $this->assertFalse($leaf['covered'], "Seed: {$seed}, iteration: {$i}, file: {$file}");
                $this->assertEquals(0.0, $leaf['coverage'], "Seed: {$seed}, iteration: {$i}, file: {$file}");
            }
        }
    }

    /**
     * Property 19: Covered File Coverage Display
     *
     * Files in both repo and coverage should display their actual coverage.
     */
    public function test_property_19_covered_file_coverage_display(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $report = CoverageReport::factory()->create();
            $percentage = fake()->randomFloat(2, 0, 100);

            CoverageFile::factory()->create([
                'coverage_report_id' => $report->id,
                'file_path' => 'src/Covered.php',
                'coverage_percentage' => $percentage,
            ]);

            $tree = $this->builder->build($report, ['src/Covered.php']);
            $node = $tree['src']['children']['Covered.php'];

            $this->assertEquals(
                $percentage,
                $node['coverage'],
                "Seed: {$seed}, iteration: {$i}"
            );
        }
    }

    /**
     * Property 20: File Exclusion Pattern Filtering
     *
     * Files matching exclusion patterns should be filtered out.
     */
    public function test_property_20_file_exclusion_pattern_filtering(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $keepCount = rand(1, 5);
            $excludeCount = rand(1, 5);

            $keepFiles = [];
            $excludeFiles = [];

            for ($k = 0; $k < $keepCount; $k++) {
                $keepFiles[] = 'src/Keep_'.$k.'.php';
            }
            for ($e = 0; $e < $excludeCount; $e++) {
                $excludeFiles[] = 'vendor/Package_'.$e.'/File.php';
            }

            $allFiles = array_merge($keepFiles, $excludeFiles);
            $filtered = $this->builder->applyExclusionPatterns($allFiles, ['vendor/*']);

            $this->assertCount(
                $keepCount,
                $filtered,
                "Seed: {$seed}, iteration: {$i}"
            );

            foreach ($excludeFiles as $excluded) {
                $this->assertNotContains(
                    $excluded,
                    $filtered,
                    "Seed: {$seed}, iteration: {$i}"
                );
            }
        }
    }
}
