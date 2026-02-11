<?php

namespace Tests\Unit\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\InvalidCloverFormatException;
use App\Services\CloverParser;
use Tests\TestCase;

class CloverParserTest extends TestCase
{
    private CloverParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CloverParser;
    }

    public function test_parses_valid_clover_xml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="0"/>
      <line num="3" type="stmt" count="5"/>
      <line num="4" type="method" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals(66.67, $result['overall_percentage']);
        $this->assertEquals(3, $result['total_lines']);
        $this->assertEquals(2, $result['covered_lines']);
        $this->assertCount(1, $result['files']);
        $this->assertEquals('src/Foo.php', $result['files'][0]['path']);
    }

    public function test_parses_multiple_files(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="1"/>
    </file>
    <file name="src/Bar.php">
      <line num="1" type="stmt" count="0"/>
      <line num="2" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertCount(2, $result['files']);
        $this->assertEquals(50.0, $result['overall_percentage']);
        $this->assertEquals(4, $result['total_lines']);
        $this->assertEquals(2, $result['covered_lines']);
    }

    public function test_handles_empty_coverage_file(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals(0.0, $result['overall_percentage']);
        $this->assertEquals(0, $result['total_lines']);
        $this->assertEmpty($result['files']);
    }

    public function test_handles_file_with_zero_lines(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Empty.php">
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertCount(1, $result['files']);
        $this->assertEquals(0.0, $result['files'][0]['percentage']);
        $this->assertEquals(0, $result['files'][0]['total_lines']);
    }

    public function test_only_counts_stmt_type_lines(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="method" count="1"/>
      <line num="2" type="stmt" count="1"/>
      <line num="3" type="cond" count="0"/>
      <line num="4" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals(2, $result['files'][0]['total_lines']);
        $this->assertEquals(1, $result['files'][0]['covered_lines']);
        $this->assertEquals(50.0, $result['files'][0]['percentage']);
    }

    public function test_throws_exception_for_missing_file(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->parser->parse('/nonexistent/path/clover.xml');
    }

    public function test_throws_exception_for_malformed_xml(): void
    {
        $this->expectException(InvalidCloverFormatException::class);

        $path = $this->writeTempFile('not valid xml <<<<');
        $this->parser->parse($path);
    }

    public function test_extracts_line_coverage_data(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="5" type="stmt" count="3"/>
      <line num="10" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);
        $lines = $result['files'][0]['lines'];

        $this->assertArrayHasKey(5, $lines);
        $this->assertTrue($lines[5]['covered']);
        $this->assertEquals(3, $lines[5]['count']);

        $this->assertArrayHasKey(10, $lines);
        $this->assertFalse($lines[10]['covered']);
        $this->assertEquals(0, $lines[10]['count']);
    }

    public function test_100_percent_coverage(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="3"/>
      <line num="3" type="stmt" count="7"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals(100.0, $result['overall_percentage']);
        $this->assertEquals(100.0, $result['files'][0]['percentage']);
    }

    public function test_zero_percent_coverage(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="0"/>
      <line num="2" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals(0.0, $result['overall_percentage']);
        $this->assertEquals(0.0, $result['files'][0]['percentage']);
    }

    public function test_strips_absolute_path_prefix_automatically(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="/Users/john/Sites/myproject/src/Foo.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/Users/john/Sites/myproject/src/Bar.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/Users/john/Sites/myproject/tests/FooTest.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals('src/Foo.php', $result['files'][0]['path']);
        $this->assertEquals('src/Bar.php', $result['files'][1]['path']);
        $this->assertEquals('tests/FooTest.php', $result['files'][2]['path']);
    }

    public function test_handles_relative_paths_without_stripping(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="tests/FooTest.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals('src/Foo.php', $result['files'][0]['path']);
        $this->assertEquals('tests/FooTest.php', $result['files'][1]['path']);
    }

    public function test_strips_ci_cd_paths_correctly(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="/home/runner/work/my-repo/my-repo/src/Controllers/HomeController.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/home/runner/work/my-repo/my-repo/src/Models/User.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/home/runner/work/my-repo/my-repo/tests/Feature/ExampleTest.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path);

        $this->assertEquals('src/Controllers/HomeController.php', $result['files'][0]['path']);
        $this->assertEquals('src/Models/User.php', $result['files'][1]['path']);
        $this->assertEquals('tests/Feature/ExampleTest.php', $result['files'][2]['path']);
    }

    public function test_uses_repository_files_for_accurate_path_matching(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="/Users/rickgout/Sites/lento-api/src/Invoicing/Domain/Events/InvoiceFinalizedEvent.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/Users/rickgout/Sites/lento-api/src/Contracts/Public/RentalContractInterface.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $knownFiles = [
            'src/Invoicing/Domain/Events/InvoiceFinalizedEvent.php',
            'src/Contracts/Public/RentalContractInterface.php',
            'src/Contracts/Public/Data/RentalContractData.php',
            'tests/Feature/InvoicingTest.php',
        ];

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path, $knownFiles);

        $this->assertEquals('src/Invoicing/Domain/Events/InvoiceFinalizedEvent.php', $result['files'][0]['path']);
        $this->assertEquals('src/Contracts/Public/RentalContractInterface.php', $result['files'][1]['path']);
    }

    public function test_matching_works_even_when_all_files_in_same_directory(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="/home/runner/work/my-repo/my-repo/src/Controllers/HomeController.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/home/runner/work/my-repo/my-repo/src/Controllers/ApiController.php">
      <line num="1" type="stmt" count="1"/>
    </file>
    <file name="/home/runner/work/my-repo/my-repo/src/Models/User.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $knownFiles = [
            'src/Controllers/HomeController.php',
            'src/Controllers/ApiController.php',
            'src/Models/User.php',
        ];

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path, $knownFiles);

        $this->assertEquals('src/Controllers/HomeController.php', $result['files'][0]['path']);
        $this->assertEquals('src/Controllers/ApiController.php', $result['files'][1]['path']);
        $this->assertEquals('src/Models/User.php', $result['files'][2]['path']);
    }

    public function test_falls_back_to_auto_detection_when_no_matches(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="/Users/john/Sites/myproject/src/Foo.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $knownFiles = [
            'lib/Bar.php',
            'tests/BazTest.php',
        ];

        $path = $this->writeTempFile($xml);
        $result = $this->parser->parse($path, $knownFiles);

        // Should fall back to auto-detection, find src/ as common root
        $this->assertEquals('src/Foo.php', $result['files'][0]['path']);
    }

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clover_test_');
        file_put_contents($path, $content);

        return $path;
    }
}
