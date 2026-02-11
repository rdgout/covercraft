<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageRequest;
use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;

class CoverageController extends Controller
{
    public function store(StoreCoverageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$owner, $name] = explode('/', $validated['repository'], 2);

        $repository = Repository::firstOrCreate(
            ['owner' => $owner, 'name' => $name],
            ['github_url' => "https://github.com/{$owner}/{$name}"],
        );

        $timestamp = now()->format('YmdHis');
        $sanitizedRepo = str_replace('/', '_', $validated['repository']);
        $sanitizedBranch = str_replace('/', '_', $validated['branch']);
        $shortCommit = substr($validated['commit_sha'], 0, 8);
        $filename = "{$sanitizedRepo}_{$sanitizedBranch}_{$shortCommit}_{$timestamp}.xml";

        $path = $request->file('clover_file')->storeAs(
            'coverage',
            $filename,
            config('coverage.storage_disk'),
        );

        $report = CoverageReport::create([
            'repository_id' => $repository->id,
            'branch' => $validated['branch'],
            'commit_sha' => $validated['commit_sha'],
            'clover_file_path' => $path,
            'status' => 'pending',
        ]);

        ProcessCoverageJob::dispatch($report->id);

        return response()->json([
            'status' => 'queued',
            'report_id' => $report->id,
            'message' => 'Coverage processing queued',
        ], 202);
    }

    public function status(CoverageReport $report): JsonResponse
    {
        $data = [
            'status' => $report->status,
            'report_id' => $report->id,
        ];

        if ($report->status === 'failed') {
            $data['error'] = $report->error_message;
        }

        return response()->json($data);
    }
}
