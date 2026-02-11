<?php

namespace Tests\Unit\Services;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Services\FileTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileTreeBuilderTest extends TestCase
{
    use RefreshDatabase;

    private FileTreeBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FileTreeBuilder;
    }

    public function test_builds_hierarchical_tree_from_flat_files(): void
    {
        $report = CoverageReport::factory()->create();

        $tree = $this->builder->build($report, [
            'src/Controllers/HomeController.php',
            'src/Models/User.php',
            'README.md',
        ]);

        $this->assertArrayHasKey('src', $tree);
        $this->assertEquals('directory', $tree['src']['type']);
        $this->assertArrayHasKey('Controllers', $tree['src']['children']);
        $this->assertArrayHasKey('README.md', $tree);
        $this->assertEquals('file', $tree['README.md']['type']);
    }

    public function test_merges_coverage_with_repository_files(): void
    {
        $report = CoverageReport::factory()->create();
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/Foo.php',
            'coverage_percentage' => 85.50,
        ]);

        $tree = $this->builder->build($report, ['src/Foo.php', 'src/Bar.php']);

        $fooNode = $tree['src']['children']['Foo.php'];
        $barNode = $tree['src']['children']['Bar.php'];

        $this->assertTrue($fooNode['covered']);
        $this->assertEquals(85.50, $fooNode['coverage']);
        $this->assertFalse($barNode['covered']);
        $this->assertEquals(0.0, $barNode['coverage']);
    }

    public function test_handles_empty_file_list(): void
    {
        $report = CoverageReport::factory()->create();

        $tree = $this->builder->build($report, []);

        $this->assertEmpty($tree);
    }

    public function test_handles_empty_coverage(): void
    {
        $report = CoverageReport::factory()->create();

        $tree = $this->builder->build($report, ['src/Foo.php']);

        $this->assertFalse($tree['src']['children']['Foo.php']['covered']);
    }

    public function test_calculates_directory_coverage(): void
    {
        $report = CoverageReport::factory()->create();
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/A.php',
            'coverage_percentage' => 80.00,
        ]);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/B.php',
            'coverage_percentage' => 60.00,
        ]);

        $tree = $this->builder->build($report, ['src/A.php', 'src/B.php']);

        $this->assertEquals(70.0, $tree['src']['coverage']);
        $this->assertEquals(2, $tree['src']['file_count']);
    }

    public function test_exclusion_patterns_filter_files(): void
    {
        $files = [
            'src/App.php',
            'vendor/autoload.php',
            'vendor/laravel/framework/src/Foo.php',
            'node_modules/vue/index.js',
            'tests/Unit/FooTest.php',
        ];

        $filtered = $this->builder->applyExclusionPatterns($files, [
            'vendor/*',
            'node_modules/*',
        ]);

        $this->assertEquals(['src/App.php', 'tests/Unit/FooTest.php'], $filtered);
    }

    public function test_exclusion_with_empty_patterns(): void
    {
        $files = ['src/A.php', 'src/B.php'];

        $filtered = $this->builder->applyExclusionPatterns($files, []);

        $this->assertEquals($files, $filtered);
    }

    public function test_exclusion_with_empty_files(): void
    {
        $filtered = $this->builder->applyExclusionPatterns([], ['vendor/*']);

        $this->assertEmpty($filtered);
    }

    public function test_nested_directory_coverage_calculation(): void
    {
        $report = CoverageReport::factory()->create();
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/Http/Controllers/HomeController.php',
            'coverage_percentage' => 100.00,
        ]);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/Http/Controllers/ApiController.php',
            'coverage_percentage' => 50.00,
        ]);

        $tree = $this->builder->build($report, [
            'src/Http/Controllers/HomeController.php',
            'src/Http/Controllers/ApiController.php',
        ]);

        $this->assertEquals(75.0, $tree['src']['coverage']);
        $this->assertEquals(75.0, $tree['src']['children']['Http']['coverage']);
        $this->assertEquals(75.0, $tree['src']['children']['Http']['children']['Controllers']['coverage']);
    }
}
