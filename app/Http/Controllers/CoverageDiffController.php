<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageDiffRequest;
use App\Jobs\ProcessCoverageDiffJob;
use App\Models\CoverageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;

class CoverageDiffController extends Controller
{
    public function store(StoreCoverageDiffRequest $request, CoverageReport $report): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('diff_file');
        $checksum = hash_file('sha256', $file->getRealPath());

        if (! hash_equals($validated['sha256'], $checksum)) {
            return response()->json(['error' => 'The diff checksum does not match the uploaded artifact.'], 422);
        }

        $stream = fopen($file->getRealPath(), 'rb');
        $encoding = fread($stream, 2) === "\x1f\x8b" ? 'gzip' : 'plain';
        fclose($stream);
        $metadata = [
            'encoding' => $encoding,
            'file_count' => (int) $validated['file_count'],
            'added_lines' => (int) $validated['added_lines'],
            'deleted_lines' => (int) $validated['deleted_lines'],
            'base_branch' => $validated['base_branch'] ?? null,
            'comparison_type' => $validated['comparison_type'] ?? null,
        ];

        try {
            $response = $report->getConnection()->transaction(function () use ($report, $file, $checksum, $validated, $metadata): JsonResponse {
                $fresh = CoverageReport::query()->lockForUpdate()->findOrFail($report->id);

                if ($fresh->diff_sha256 !== null) {
                    $same = $fresh->diff_sha256 === $checksum && $fresh->diff_base_sha === $validated['base_commit_sha'];
                    foreach ($metadata as $key => $value) {
                        $same = $same && ($fresh->diff_metadata[$key] ?? null) === $value;
                    }

                    return $same
                        ? response()->json(['report_id' => $fresh->id, 'diff_status' => $fresh->diff_status], 202)
                        : response()->json(['error' => 'A different diff is already attached to this report. Upload a new coverage report to change it.'], 409);
                }

                $path = $file->storeAs('coverage/diffs', Str::uuid().'.diff', config('coverage.storage_disk'));
                if ($path === false) {
                    throw UnableToWriteFile::atLocation('coverage/diffs', 'The storage disk rejected the upload.');
                }

                $fresh->update([
                    'diff_file_path' => $path,
                    'diff_sha256' => $checksum,
                    'diff_base_sha' => $validated['base_commit_sha'],
                    'diff_status' => 'queued',
                    'diff_metadata' => $metadata + ['scope' => [
                        'include_patterns' => config('coverage.diff.include_patterns'),
                        'exclude_patterns' => array_merge(config('coverage.exclude_patterns'), config('coverage.diff.exclude_patterns')),
                    ]],
                    'patch_coverage' => ['status' => $fresh->status === 'completed' ? 'pending' : 'awaiting_coverage'],
                ]);
                ProcessCoverageDiffJob::dispatch($fresh->id, $checksum)->afterCommit();

                return response()->json(['report_id' => $fresh->id, 'diff_status' => 'queued', 'patch_coverage' => $fresh->patch_coverage], 202);
            });
        } catch (FilesystemException $exception) {
            report($exception);

            return response()->json(['error' => 'The diff could not be stored. Please try again later.'], 500);
        }

        return $response;
    }
}
