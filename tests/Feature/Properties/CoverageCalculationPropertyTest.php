<?php

namespace Tests\Feature\Properties;

use App\Services\CloverParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageCalculationPropertyTest extends TestCase
{
    use RefreshDatabase;

    private CloverParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CloverParser;
    }

    /**
     * Property 4: Complete File Path Extraction
     *
     * For any valid clover.xml file, the parser should extract all file paths
     * present in the XML, with no files omitted from the parsed output.
     */
    public function test_property_4_complete_file_path_extraction(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $fileCount = rand(1, 10);
            $filePaths = [];
            $fileXml = '';

            for ($f = 0; $f < $fileCount; $f++) {
                $path = "src/File{$f}_{$i}.php";
                $filePaths[] = $path;
                $fileXml .= "<file name=\"{$path}\"><line num=\"1\" type=\"stmt\" count=\"1\"/></file>";
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project>{$fileXml}</project></coverage>";
            $path = tempnam(sys_get_temp_dir(), 'prop4_');
            file_put_contents($path, $xml);

            $result = $this->parser->parse($path);

            $this->assertCount(
                $fileCount,
                $result['files'],
                "Seed: {$seed}, iteration: {$i} - expected {$fileCount} files"
            );

            $extractedPaths = array_column($result['files'], 'path');
            foreach ($filePaths as $expected) {
                $this->assertContains($expected, $extractedPaths, "Seed: {$seed}, iteration: {$i}");
            }

            unlink($path);
        }
    }

    /**
     * Property 5: Line Coverage Extraction
     *
     * For any file in a clover.xml report, the parser should extract
     * line-level coverage data for all executable lines in that file.
     */
    public function test_property_5_line_coverage_extraction(): void
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

            $xml = "<?xml version=\"1.0\"?><coverage><project><file name=\"test.php\">{$lineXml}</file></project></coverage>";
            $path = tempnam(sys_get_temp_dir(), 'prop5_');
            file_put_contents($path, $xml);

            $result = $this->parser->parse($path);
            $lines = $result['files'][0]['lines'];

            $this->assertCount(
                $lineCount,
                $lines,
                "Seed: {$seed}, iteration: {$i}"
            );

            foreach ($expectedLines as $num => $expected) {
                $this->assertEquals($expected, $lines[$num], "Seed: {$seed}, iteration: {$i}, line: {$num}");
            }

            unlink($path);
        }
    }

    /**
     * Property 6: Coverage Calculation Correctness
     *
     * For any file with N total executable lines and M covered lines,
     * the calculated coverage percentage should equal round((M / N) * 100, 2), or 0.0 if N is zero.
     */
    public function test_property_6_coverage_calculation_correctness(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $totalLines = rand(1, 50);
            $coveredLines = rand(0, $totalLines);
            $lineXml = '';

            for ($l = 1; $l <= $totalLines; $l++) {
                $count = $l <= $coveredLines ? rand(1, 10) : 0;
                $lineXml .= "<line num=\"{$l}\" type=\"stmt\" count=\"{$count}\"/>";
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project><file name=\"test.php\">{$lineXml}</file></project></coverage>";
            $path = tempnam(sys_get_temp_dir(), 'prop6_');
            file_put_contents($path, $xml);

            $result = $this->parser->parse($path);
            $expected = round(($coveredLines / $totalLines) * 100, 2);

            $this->assertEquals(
                $expected,
                $result['files'][0]['percentage'],
                "Seed: {$seed}, iteration: {$i}"
            );

            unlink($path);
        }
    }

    /**
     * Property 7: Overall Coverage Aggregation
     *
     * For any coverage report containing multiple files, the overall coverage percentage
     * should equal the sum of all covered lines divided by the sum of all total lines.
     */
    public function test_property_7_overall_coverage_aggregation(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $fileCount = rand(1, 5);
            $fileXml = '';
            $totalAllLines = 0;
            $coveredAllLines = 0;

            for ($f = 0; $f < $fileCount; $f++) {
                $totalLines = rand(1, 30);
                $coveredLines = rand(0, $totalLines);
                $totalAllLines += $totalLines;
                $coveredAllLines += $coveredLines;

                $lineXml = '';
                for ($l = 1; $l <= $totalLines; $l++) {
                    $count = $l <= $coveredLines ? rand(1, 10) : 0;
                    $lineXml .= "<line num=\"{$l}\" type=\"stmt\" count=\"{$count}\"/>";
                }

                $fileXml .= "<file name=\"src/File{$f}.php\">{$lineXml}</file>";
            }

            $xml = "<?xml version=\"1.0\"?><coverage><project>{$fileXml}</project></coverage>";
            $path = tempnam(sys_get_temp_dir(), 'prop7_');
            file_put_contents($path, $xml);

            $result = $this->parser->parse($path);
            $expected = $totalAllLines > 0
                ? round(($coveredAllLines / $totalAllLines) * 100, 2)
                : 0.0;

            $this->assertEquals(
                $expected,
                $result['overall_percentage'],
                "Seed: {$seed}, iteration: {$i}"
            );

            unlink($path);
        }
    }
}
