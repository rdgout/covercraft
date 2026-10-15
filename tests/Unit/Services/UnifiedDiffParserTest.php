<?php

namespace Tests\Unit\Services;

use App\Services\UnifiedDiffParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnifiedDiffParserTest extends TestCase
{
    private function parse(string $text, string $encoding = 'plain'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'coverage-diff-test-');
        file_put_contents($path, $text);
        try {
            return iterator_to_array((new UnifiedDiffParser)->parseFile($path, $encoding));
        } finally {
            unlink($path);
        }
    }

    private function header(string $path = 'src/Foo.php'): string
    {
        return "diff --git a/{$path} b/{$path}\nindex abc..def 100644\n--- a/{$path}\n+++ b/{$path}\n";
    }

    public function test_parses_replacements_context_multiple_hunks_and_files(): void
    {
        $files = $this->parse($this->header()."@@ -2,2 +2,3 @@\n context\n-old\n+new\n+second\n@@ -9 +10 @@\n-old\n+new\n".$this->header('src/Bar.php')."@@ -0,0 +1 @@\n+added\n");
        $this->assertCount(2, $files);
        $this->assertSame([['start' => 3, 'end' => 4], ['start' => 10, 'end' => 10]], $files[0]['added_ranges']);
        $this->assertSame(3, $files[0]['additions']);
        $this->assertSame(2, $files[0]['deletions']);
        $this->assertSame([['start' => 1, 'end' => 1]], $files[1]['added_ranges']);
    }

    public function test_handles_header_like_source_and_no_newline_markers(): void
    {
        $file = $this->parse($this->header()."@@ -1 +1,2 @@\n--- text\n+++ text\n+diff --git text\n\\ No newline at end of file\n")[0];
        $this->assertSame(2, $file['additions']);
        $this->assertSame(1, $file['deletions']);
    }

    public function test_handles_renames_deleted_binary_mode_only_and_quoted_paths(): void
    {
        $text = "diff --git a/old.php b/new.php\nsimilarity index 100%\nrename from old.php\nrename to new.php\n";
        $text .= $this->header('src/Deleted.php')."@@ -1 +0,0 @@\n-old\n";
        $text .= "diff --git a/image.png b/image.png\nindex abc..def 100644\nBinary files a/image.png and b/image.png differ\n";
        $text .= "diff --git a/File Name.php b/File Name.php\nold mode 100644\nnew mode 100755\n";
        $text .= "diff --git \"a/src/Tab\\tFile.php\" \"b/src/Tab\\tFile.php\"\n--- \"a/src/Tab\\tFile.php\"\n+++ \"b/src/Tab\\tFile.php\"\n@@ -0,0 +1 @@\n+new\n";
        $files = $this->parse($text);
        $this->assertSame('old.php', $files[0]['previous_path']);
        $this->assertSame('new.php', $files[0]['path']);
        $this->assertSame([], $files[1]['added_ranges']);
        $this->assertSame('binary', $files[2]['kind']);
        $this->assertSame('File Name.php', $files[3]['path']);
        $this->assertSame("src/Tab\tFile.php", $files[4]['path']);
    }

    public function test_handles_plain_and_gzip_empty_diffs_and_gzip_crc(): void
    {
        $this->assertSame([], $this->parse(''));
        $this->assertSame([], $this->parse(gzencode(''), 'gzip'));
        $text = $this->header()."@@ -0,0 +1 @@\n+new\n";
        $this->assertSame($this->parse($text), $this->parse(gzencode($text), 'gzip'));
        $this->expectException(InvalidArgumentException::class);
        $this->parse(substr(gzencode($text), 0, -4), 'gzip');
    }

    #[DataProvider('malformedDiffs')]
    public function test_rejects_malformed_or_truncated_data(string $suffix): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parse($this->header().$suffix);
    }

    public static function malformedDiffs(): array
    {
        return [
            ["@@ -1 +1,2 @@\n-old\n+new\n"],
            ["@@@ -1 -1 +1 @@@\n+new\n"],
            ["@@ -0 +0 @@\n-old\n+new\n"],
            ["@@ -0,0 +1 @@\n+new\n+extra\n"],
            ["@@ -0,0 +1 @@\n+new\n@@ -0,0 +1 @@\n+overlap\n"],
        ];
    }

    public function test_enforces_uncompressed_line_file_and_hunk_limits(): void
    {
        config(['coverage.diff.max_uncompressed_bytes' => 20]);
        try {
            $this->parse(gzencode($this->header()), 'gzip');
            $this->fail('Expected decompression limit.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('diff_limit_exceeded', $exception->getMessage());
        }
        config(['coverage.diff.max_uncompressed_bytes' => 10000, 'coverage.diff.max_line_bytes' => 10]);
        $this->expectExceptionMessage('diff_limit_exceeded');
        $this->parse($this->header());
    }

    public function test_streams_a_large_compressed_branch_with_bounded_memory(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'coverage-large-diff-');
        $stream = gzopen($path, 'wb');
        for ($file = 0; $file < 10000; $file++) {
            gzwrite($stream, $this->header('src/F'.$file.'.php')."@@ -0,0 +1,100 @@\n");
            for ($line = 0; $line < 100; $line++) {
                gzwrite($stream, '+'.str_repeat('source text ', 10)."\n");
            }
        }
        gzclose($stream);
        $baseline = memory_get_peak_usage(true);
        $files = 0;
        $additions = 0;
        try {
            foreach ((new UnifiedDiffParser)->parseFile($path, 'gzip') as $record) {
                $files++;
                $additions += $record['additions'];
                $this->assertSame([['start' => 1, 'end' => 100]], $record['added_ranges']);
            }
        } finally {
            unlink($path);
        }
        $this->assertSame(10000, $files);
        $this->assertSame(1000000, $additions);
        $this->assertLessThan(16 * 1024 * 1024, memory_get_peak_usage(true) - $baseline);
    }

    public function test_rejects_file_and_hunk_limit_overflow(): void
    {
        config(['coverage.diff.max_files' => 1]);
        try {
            $this->parse($this->header()."@@ -0,0 +1 @@\n+new\n".$this->header('src/Bar.php'));
            $this->fail('Expected file limit.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('diff_limit_exceeded', $exception->getMessage());
        }
        config(['coverage.diff.max_files' => 100, 'coverage.diff.max_hunks' => 1]);
        $this->expectExceptionMessage('diff_limit_exceeded');
        $this->parse($this->header()."@@ -0,0 +1 @@\n+new\n@@ -1,0 +3 @@\n+next\n");
    }

    public function test_exact_range_limit_still_allows_contiguous_additions(): void
    {
        config(['coverage.diff.max_ranges_per_file' => 1]);
        $record = $this->parse($this->header()."@@ -0,0 +1,2 @@\n+first\n+second\n")[0];
        $this->assertSame([['start' => 1, 'end' => 2]], $record['added_ranges']);
        $this->expectExceptionMessage('diff_limit_exceeded');
        $this->parse($this->header()."@@ -0,0 +1 @@\n+first\n@@ -1,0 +3 @@\n+second\n");
    }
}
