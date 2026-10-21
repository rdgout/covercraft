<?php

namespace App\Services;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use Generator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PatchCoverageCalculator
{
    /** @return array<string, mixed> */
    public function calculate(CoverageReport $report): array
    {
        $result = [
            'version' => 1,
            'status' => 'completed',
            'base_sha' => $report->diff_base_sha,
            'head_sha' => $report->commit_sha,
            'artifact_sha256' => $report->diff_sha256,
            'base_branch' => $report->diff_metadata['base_branch'] ?? null,
            'comparison_type' => $report->diff_metadata['comparison_type'] ?? null,
            'scope' => $report->diff_metadata['scope'],
            'covered_lines' => 0,
            'total_lines' => 0,
            'percentage' => null,
            'file_count' => 0,
            'incomplete_files' => 0,
            'files' => [],
            'evaluated_at' => now()->toIso8601String(),
        ];
        $batch = [];
        foreach ($this->index($report) as $record) {
            if (! $this->eligible($report, $record)) {
                continue;
            }
            $batch[] = $record;
            if (count($batch) >= (int) config('coverage.diff.batch_size')) {
                $this->calculateBatch($report, $batch, $result);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->calculateBatch($report, $batch, $result);
        }
        $result['status'] = $result['incomplete_files'] > 0 ? 'incomplete' : ($result['total_lines'] > 0 ? 'completed' : 'no_executable_changes');
        if ($result['status'] === 'completed') {
            $result['percentage'] = round(100 * $result['covered_lines'] / $result['total_lines'], 2);
        }
        $result['files_truncated'] = $result['file_count'] > count($result['files']);

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function fileCoverage(CoverageReport $report, CoverageFile $file): ?array
    {
        if ($report->diff_status !== 'completed' || $report->diff_index_path === null) {
            return null;
        }
        foreach ($this->index($report) as $record) {
            if ($record['path'] === $file->file_path && $this->eligible($report, $record)) {
                return $this->measure($record, $file);
            }
        }

        return null;
    }

    /** @return Generator<int, array<string, mixed>> */
    private function index(CoverageReport $report): Generator
    {
        $stream = Storage::disk(config('coverage.storage_disk'))->readStream($report->diff_index_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('storage_failed');
        }
        try {
            while (($line = fgets($stream)) !== false) {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
            if (! feof($stream)) {
                throw new RuntimeException('storage_failed');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, mixed> $record */
    private function eligible(CoverageReport $report, array $record): bool
    {
        $scope = $report->diff_metadata['scope'];

        return $record['kind'] !== 'deleted'
            && ($record['additions'] > 0 || $record['kind'] === 'binary')
            && Str::is($scope['include_patterns'], $record['path'])
            && ! Str::is($scope['exclude_patterns'], $record['path']);
    }

    /** @param list<array<string, mixed>> $batch
     * @param  array<string, mixed>  $result
     */
    private function calculateBatch(CoverageReport $report, array $batch, array &$result): void
    {
        $files = $report->files()->whereIn('file_path', array_column($batch, 'path'))->get()->keyBy('file_path');
        foreach ($batch as $record) {
            $measured = $this->measure($record, $files->get($record['path']));
            $result['file_count']++;
            $result['covered_lines'] += $measured['covered_lines'];
            $result['total_lines'] += $measured['total_lines'];
            $result['incomplete_files'] += $measured['status'] === 'incomplete' ? 1 : 0;
            if (count($result['files']) < 100) {
                unset($measured['added_ranges'], $measured['uncovered_ranges']);
                $result['files'][] = $measured;
            }
        }
    }

    /** @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function measure(array $record, ?CoverageFile $file): array
    {
        $result = ['path' => $record['path'], 'previous_path' => $record['previous_path'], 'status' => 'completed', 'reason' => null,
            'covered_lines' => 0, 'total_lines' => 0, 'percentage' => null, 'added_ranges' => $record['added_ranges'], 'uncovered_ranges' => []];
        if ($record['kind'] === 'binary' || $file === null) {
            $result['status'] = 'incomplete';
            $result['reason'] = $record['kind'] === 'binary' ? 'binary_source' : 'missing_coverage_file';

            return $result;
        }
        try {
            $encoded = base64_decode($file->line_coverage_data, true);
            $decoded = $encoded === false ? false : @gzuncompress($encoded, (int) config('coverage.diff.max_coverage_file_bytes'));
            $coverage = $decoded === false ? null : json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($coverage)) {
                throw new RuntimeException('invalid_coverage');
            }
            ksort($coverage, SORT_NUMERIC);
            $rangeIndex = 0;
            foreach ($coverage as $number => $data) {
                if (! is_numeric($number) || ! is_array($data) || ! is_bool($data['covered'] ?? null) || ! is_int($data['count'] ?? null) || $data['count'] < 0) {
                    throw new RuntimeException('invalid_coverage');
                }
                while (isset($record['added_ranges'][$rangeIndex]) && $record['added_ranges'][$rangeIndex]['end'] < (int) $number) {
                    $rangeIndex++;
                }
                $range = $record['added_ranges'][$rangeIndex] ?? null;
                if ($range === null || (int) $number < $range['start']) {
                    continue;
                }
                $result['total_lines']++;
                if ($data['count'] > 0) {
                    $result['covered_lines']++;
                } else {
                    $last = array_key_last($result['uncovered_ranges']);
                    if ($last !== null && $result['uncovered_ranges'][$last]['end'] === (int) $number - 1) {
                        $result['uncovered_ranges'][$last]['end'] = (int) $number;
                    } else {
                        $result['uncovered_ranges'][] = ['start' => (int) $number, 'end' => (int) $number];
                    }
                }
            }
        } catch (Throwable) {
            return array_replace($result, ['status' => 'incomplete', 'reason' => 'invalid_coverage', 'covered_lines' => 0, 'total_lines' => 0, 'uncovered_ranges' => []]);
        }
        $result['status'] = $result['total_lines'] > 0 ? 'completed' : 'no_executable_changes';
        if ($result['total_lines'] > 0) {
            $result['percentage'] = round(100 * $result['covered_lines'] / $result['total_lines'], 2);
        }

        return $result;
    }
}
