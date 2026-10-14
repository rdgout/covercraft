<?php

namespace App\Jobs;

use App\Actions\QueueChangedLineCoverageAction;
use App\Models\CoverageReport;
use App\Services\UnifiedDiffParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProcessCoverageDiffJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $coverageReportId, public string $artifactSha256) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('coverage-diff:'.$this->coverageReportId))->releaseAfter(10)->expireAfter(90)];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(UnifiedDiffParser $parser, QueueChangedLineCoverageAction $ready): void
    {
        $report = CoverageReport::query()->find($this->coverageReportId);
        if ($report === null || $report->diff_sha256 !== $this->artifactSha256 || $report->diff_status === 'failed') {
            return;
        }
        if ($report->diff_status === 'completed') {
            $ready->execute($report);

            return;
        }
        $report->update(['diff_status' => 'processing']);
        $disk = Storage::disk(config('coverage.storage_disk'));
        $input = tmpfile();
        $output = tmpfile();
        $source = null;
        $indexPath = 'coverage/diffs/'.Str::uuid().'.jsonl';
        $published = false;
        try {
            $source = $disk->readStream($report->diff_file_path);
            if ($source === null || $source === false || $input === false || $output === false) {
                throw new RuntimeException('storage_failed');
            }
            if (stream_copy_to_stream($source, $input) === false) {
                throw new RuntimeException('storage_failed');
            }
            $path = stream_get_meta_data($input)['uri'];
            if (! hash_equals($this->artifactSha256, hash_file('sha256', $path))) {
                throw new InvalidArgumentException('checksum_mismatch');
            }
            $counts = ['file_count' => 0, 'added_lines' => 0, 'deleted_lines' => 0];
            $seen = [];
            foreach ($parser->parseFile($path, $report->diff_metadata['encoding']) as $file) {
                $pathKey = hash('sha256', $file['path']);
                if (isset($seen[$pathKey])) {
                    throw new InvalidArgumentException('invalid_diff');
                }
                $seen[$pathKey] = true;
                $counts['file_count']++;
                $counts['added_lines'] += $file['additions'];
                $counts['deleted_lines'] += $file['deletions'];
                $line = json_encode($file, JSON_THROW_ON_ERROR)."\n";
                if (fwrite($output, $line) !== strlen($line)) {
                    throw new RuntimeException('storage_failed');
                }
            }
            foreach ($counts as $key => $count) {
                if ($count !== $report->diff_metadata[$key]) {
                    throw new InvalidArgumentException('count_mismatch');
                }
            }
            rewind($output);
            if (! $disk->put($indexPath, $output)) {
                throw new RuntimeException('storage_failed');
            }
            $report->getConnection()->transaction(function () use ($indexPath, &$published): void {
                $fresh = CoverageReport::query()->lockForUpdate()->find($this->coverageReportId);
                if ($fresh !== null && $fresh->diff_sha256 === $this->artifactSha256 && $fresh->diff_status !== 'completed') {
                    $fresh->update(['diff_index_path' => $indexPath, 'diff_status' => 'completed', 'diff_error_code' => null]);
                    $published = true;
                }
            });
            $ready->execute($report);
        } catch (InvalidArgumentException $exception) {
            $this->recordFailure($exception->getMessage());
        } finally {
            foreach ([$source, $input, $output] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            if (! $published && $disk->exists($indexPath)) {
                $disk->delete($indexPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->recordFailure('storage_failed');
    }

    private function recordFailure(string $reason): void
    {
        CoverageReport::query()->whereKey($this->coverageReportId)
            ->where('diff_sha256', $this->artifactSha256)
            ->where('diff_status', '!=', 'completed')
            ->update([
                'diff_status' => 'failed',
                'diff_error_code' => $reason,
                'patch_coverage' => json_encode(['status' => 'unavailable', 'reason' => $reason], JSON_THROW_ON_ERROR),
            ]);
    }
}
