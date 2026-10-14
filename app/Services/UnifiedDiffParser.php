<?php

namespace App\Services;

use Generator;
use InvalidArgumentException;
use RuntimeException;

class UnifiedDiffParser
{
    /** @return Generator<int, array{path: string, previous_path: ?string, kind: string, added_ranges: list<array{start: int, end: int}>, additions: int, deletions: int}> */
    public function parseFile(string $filePath, string $encoding): Generator
    {
        $file = null;
        $oldRemaining = 0;
        $newRemaining = 0;
        $newLine = 0;
        $lastOldEnd = 0;
        $lastNewEnd = 0;
        $fileCount = 0;
        $hunkCount = 0;

        foreach ($this->lines($filePath, $encoding) as $line) {
            if (str_starts_with($line, '\ No newline at end of file') && $file !== null) {
                continue;
            }

            if ($oldRemaining > 0 || $newRemaining > 0) {
                $prefix = $line[0] ?? '';
                if ($prefix === '+' && $newRemaining > 0) {
                    $this->appendRange($file['added_ranges'], $newLine);
                    $file['additions']++;
                    $newLine++;
                    $newRemaining--;
                } elseif ($prefix === '-' && $oldRemaining > 0) {
                    $file['deletions']++;
                    $oldRemaining--;
                } elseif ($prefix === ' ' && $oldRemaining > 0 && $newRemaining > 0) {
                    $oldRemaining--;
                    $newRemaining--;
                    $newLine++;
                } else {
                    throw new InvalidArgumentException('invalid_diff');
                }

                continue;
            }

            if (str_starts_with($line, 'diff --git ')) {
                if ($file !== null) {
                    yield $this->validatedFile($file);
                }
                if (++$fileCount > (int) config('coverage.diff.max_files')) {
                    throw new InvalidArgumentException('diff_limit_exceeded');
                }
                [$oldPath, $newPath] = $this->headerPaths(substr($line, 11));
                $file = ['path' => $newPath, 'previous_path' => $oldPath, 'kind' => 'modified', 'added_ranges' => [], 'additions' => 0, 'deletions' => 0];
                $lastOldEnd = 0;
                $lastNewEnd = 0;

                continue;
            }

            if ($file === null) {
                throw new InvalidArgumentException('invalid_diff');
            }

            if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@(?:.*)$/', $line, $matches)) {
                if ($file['kind'] === 'binary' || ++$hunkCount > (int) config('coverage.diff.max_hunks')) {
                    throw new InvalidArgumentException($file['kind'] === 'binary' ? 'invalid_diff' : 'diff_limit_exceeded');
                }
                $oldStart = (int) $matches[1];
                $newStart = (int) $matches[3];
                $oldRemaining = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 1;
                $newRemaining = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 1;
                if (($oldRemaining > 0 && ($oldStart < 1 || $oldStart < $lastOldEnd))
                    || ($newRemaining > 0 && ($newStart < 1 || $newStart < $lastNewEnd))
                    || ($oldRemaining === 0 && $newRemaining === 0)) {
                    throw new InvalidArgumentException('invalid_diff');
                }
                $lastOldEnd = $oldStart + $oldRemaining;
                $lastNewEnd = $newStart + $newRemaining;
                $newLine = $newStart;
            } elseif (str_starts_with($line, '--- ')) {
                $file['previous_path'] = $this->patchPath(substr($line, 4), 'a/');
            } elseif (str_starts_with($line, '+++ ')) {
                $newPath = $this->patchPath(substr($line, 4), 'b/');
                if ($newPath === null) {
                    $file['kind'] = 'deleted';
                    $file['path'] = $file['previous_path'];
                } else {
                    $file['path'] = $newPath;
                }
            } elseif (str_starts_with($line, 'rename from ')) {
                $file['previous_path'] = $this->decodePath(substr($line, 12));
                $file['kind'] = 'renamed';
            } elseif (str_starts_with($line, 'rename to ')) {
                $file['path'] = $this->decodePath(substr($line, 10));
            } elseif (str_starts_with($line, 'new file mode ')) {
                $file['kind'] = 'added';
            } elseif (str_starts_with($line, 'deleted file mode ')) {
                $file['kind'] = 'deleted';
            } elseif (str_starts_with($line, 'Binary files ') && str_ends_with($line, ' differ')) {
                $file['kind'] = 'binary';
            } elseif (! preg_match('/^(?:index [a-f0-9]+\.\.[a-f0-9]+(?: \d+)?|(?:old|new) mode \d+|similarity index \d+%)$/', $line)) {
                throw new InvalidArgumentException('invalid_diff');
            }
        }

        if ($oldRemaining !== 0 || $newRemaining !== 0) {
            throw new InvalidArgumentException('invalid_diff');
        }
        if ($file !== null) {
            yield $this->validatedFile($file);
        }
    }

    /** @return Generator<int, string> */
    private function lines(string $path, string $encoding): Generator
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('storage_failed');
        }
        if (! in_array($encoding, ['plain', 'gzip'], true)) {
            fclose($stream);
            throw new InvalidArgumentException('invalid_diff');
        }
        $inflater = $encoding === 'gzip' ? inflate_init(ZLIB_ENCODING_GZIP) : null;
        $buffer = '';
        $total = 0;
        $compressedBytes = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('storage_failed');
                }
                if ($chunk === '') {
                    break;
                }
                $compressedBytes += strlen($chunk);
                if ($inflater !== null) {
                    if (inflate_get_status($inflater) === ZLIB_STREAM_END) {
                        throw new InvalidArgumentException('invalid_gzip');
                    }
                    $chunk = @inflate_add($inflater, $chunk, ZLIB_SYNC_FLUSH);
                    if ($chunk === false) {
                        throw new InvalidArgumentException('invalid_gzip');
                    }
                }
                $total += strlen($chunk);
                if ($total > (int) config('coverage.diff.max_uncompressed_bytes')) {
                    throw new InvalidArgumentException('diff_limit_exceeded');
                }
                $buffer .= $chunk;
                while (($boundary = strpos($buffer, "\n")) !== false) {
                    if ($boundary > (int) config('coverage.diff.max_line_bytes')) {
                        throw new InvalidArgumentException('diff_limit_exceeded');
                    }
                    yield rtrim(substr($buffer, 0, $boundary), "\r");
                    $buffer = substr($buffer, $boundary + 1);
                }
                if (strlen($buffer) > (int) config('coverage.diff.max_line_bytes')) {
                    throw new InvalidArgumentException('diff_limit_exceeded');
                }
            }
            if ($inflater !== null && (inflate_get_status($inflater) !== ZLIB_STREAM_END || inflate_get_read_len($inflater) !== $compressedBytes)) {
                throw new InvalidArgumentException('invalid_gzip');
            }
            if ($buffer !== '') {
                yield rtrim($buffer, "\r");
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array{string, string} */
    private function headerPaths(string $header): array
    {
        if (preg_match('/^("(?:\\\\.|[^"\\\\])*"|\S+) ("(?:\\\\.|[^"\\\\])*"|\S+)$/', $header, $matches)) {
            return [$this->patchPath($matches[1], 'a/'), $this->patchPath($matches[2], 'b/')];
        }
        $length = intdiv(strlen($header) - 5, 2);
        $samePath = substr($header, 2, $length);
        if ($header === 'a/'.$samePath.' b/'.$samePath) {
            return [$samePath, $samePath];
        }
        $parts = explode(' b/', $header, 2);
        if (count($parts) !== 2 || ! str_starts_with($parts[0], 'a/')) {
            throw new InvalidArgumentException('invalid_diff');
        }

        return [substr($parts[0], 2), $parts[1]];
    }

    private function patchPath(string $value, string $prefix): ?string
    {
        if ($value === '/dev/null') {
            return null;
        }
        $path = $this->decodePath(rtrim($value, "\t"));
        if (! str_starts_with($path, $prefix)) {
            throw new InvalidArgumentException('invalid_diff');
        }

        return substr($path, strlen($prefix));
    }

    private function decodePath(string $value): string
    {
        if (str_starts_with($value, '"')) {
            if (! str_ends_with($value, '"')) {
                throw new InvalidArgumentException('invalid_diff');
            }
            $value = stripcslashes(substr($value, 1, -1));
        }
        if ($value === '' || str_contains($value, "\0")) {
            throw new InvalidArgumentException('invalid_diff');
        }

        return $value;
    }

    /** @param list<array{start: int, end: int}> $ranges */
    private function appendRange(array &$ranges, int $line): void
    {
        $last = array_key_last($ranges);
        if ($last !== null && $ranges[$last]['end'] === $line - 1) {
            $ranges[$last]['end'] = $line;
        } else {
            if (count($ranges) >= (int) config('coverage.diff.max_ranges_per_file')) {
                throw new InvalidArgumentException('diff_limit_exceeded');
            }
            $ranges[] = ['start' => $line, 'end' => $line];
        }
    }

    /** @param array<string, mixed> $file
     * @return array{path: string, previous_path: ?string, kind: string, added_ranges: list<array{start: int, end: int}>, additions: int, deletions: int}
     */
    private function validatedFile(array $file): array
    {
        if (! is_string($file['path']) || $file['path'] === '' || strlen($file['path']) > 4096
            || ! mb_check_encoding($file['path'], 'UTF-8')
            || ($file['previous_path'] !== null && (! mb_check_encoding($file['previous_path'], 'UTF-8') || strlen($file['previous_path']) > 4096))) {
            throw new InvalidArgumentException('invalid_diff');
        }
        if ($file['previous_path'] === $file['path']) {
            $file['previous_path'] = null;
        }

        return $file;
    }
}
