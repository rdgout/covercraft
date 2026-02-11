<?php

namespace Tests\Unit\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\InvalidCloverFormatException;
use App\Services\CloverParser;
use PHPUnit\Framework\TestCase;

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

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clover_test_');
        file_put_contents($path, $content);

        return $path;
    }
}
