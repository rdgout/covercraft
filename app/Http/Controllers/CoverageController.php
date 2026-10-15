<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageRequest;
use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;

class CoverageController extends Controller
{
    public function store(StoreCoverageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$owner, $name] = explode('/', $validated['repository'], 2);

        $repository = Repository::where('owner', $owner)
            ->where('name', $name)
            ->first();

        if (! $repository) {
            return response()->json([
                'error' => 'Repository not found',
            ], 404);
        }

        $authenticatedTeamId = $request->attributes->get('authenticated_team_id');

        if ($repository->team_id !== $authenticatedTeamId) {
            return response()->json([
                'error' => 'Forbidden',
            ], 403);
        }

        $timestamp = now()->format('YmdHis');
        $sanitizedRepo = str_replace('/', '_', $validated['repository']);
        $sanitizedBranch = str_replace('/', '_', $validated['branch']);
        $shortCommit = substr($validated['commit_sha'], 0, 8);
        $filename = "{$sanitizedRepo}_{$sanitizedBranch}_{$shortCommit}_{$timestamp}.xml";

        try {
            $path = $request->file('clover_file')->storeAs(
                'coverage',
                $filename,
                config('coverage.storage_disk'),
            );

            if ($path === false) {
                throw UnableToWriteFile::atLocation('coverage/'.$filename, 'The storage disk rejected the upload.');
            }
        } catch (FilesystemException $exception) {
            report($exception);

            return response()->json([
                'error' => 'Coverage file could not be stored. Please try again later.',
            ], 500);
        }

        CoverageReport::query()
            ->where('repository_id', $repository->id)
            ->where('branch', $validated['branch'])
            ->where('archived', false)
            ->update(['archived' => true, 'archived_at' => now()]);

        $report = CoverageReport::create([
            'repository_id' => $repository->id,
            'branch' => $validated['branch'],
            'commit_sha' => $validated['commit_sha'],
            'clover_file_path' => $path,
            'status' => 'pending',
            'diff_status' => 'awaiting_upload',
            'patch_coverage' => ['status' => 'awaiting_diff'],
        ]);

        ProcessCoverageJob::dispatch($report->id);

        return response()->json([
            'status' => 'queued',
            'report_id' => $report->id,
            'message' => 'Coverage processing queued',
        ], 202);
    }

    public function status(CoverageReport $report, Request $request): JsonResponse
    {
        $authenticatedTeamId = $request->attributes->get('authenticated_team_id');

        if ($report->repository->team_id !== $authenticatedTeamId) {
            return response()->json([
                'error' => 'Forbidden',
            ], 403);
        }

        $data = [
            'status' => $report->status,
            'report_id' => $report->id,
            'diff_status' => $report->diff_status,
            'diff_error' => $report->diffErrorMessage(),
            'patch_coverage' => $report->patch_coverage,
        ];

        if ($report->status === 'failed') {
            $data['error'] = $report->error_message;
        }

        return response()->json($data);
    }
}
