<?php

namespace App\Http\Controllers;

use App\Contracts\GitHubServiceInterface;
use App\Jobs\FetchRepositoryFilesJob;
use App\Jobs\PostPullRequestCommentJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Services\GitHubAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function github(Request $request, GitHubServiceInterface $githubService): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');
        $event = $request->header('X-GitHub-Event', '');

        $data = json_decode($payload, true);
        if (! $data || ! isset($data['repository'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $repository = Repository::query()
            ->where('owner', $data['repository']['owner']['login'] ?? '')
            ->where('name', $data['repository']['name'] ?? '')
            ->first();

        if (! $repository || ! $repository->webhook_secret) {
            return response()->json(['error' => 'Repository not found'], 404);
        }

        if (! $githubService->verifyWebhookSignature($payload, $signature, $repository->webhook_secret)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        if ($event === 'push' && isset($data['ref'], $data['after'])) {
            $branch = str_replace('refs/heads/', '', $data['ref']);
            FetchRepositoryFilesJob::dispatch($repository->id, $branch, $data['after']);
        }

        return response()->json(['status' => 'ok']);
    }

    public function githubApp(Request $request, GitHubAppService $githubAppService): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');

        if (! $githubAppService->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = json_decode($payload, true);
        $event = $request->header('X-GitHub-Event', '');

        if ($event === 'pull_request' && isset($data['action']) && in_array($data['action'], ['opened', 'synchronize', 'reopened'], true)) {
            $owner = $data['repository']['owner']['login'] ?? '';
            $name = $data['repository']['name'] ?? '';

            $repository = Repository::query()
                ->where('owner', $owner)
                ->where('name', $name)
                ->first();

            if ($repository) {
                $headSha = $data['pull_request']['head']['sha'] ?? null;

                if ($headSha) {
                    $report = CoverageReport::query()
                        ->where('repository_id', $repository->id)
                        ->where('commit_sha', $headSha)
                        ->where('status', 'completed')
                        ->latest()
                        ->first();

                    if ($report) {
                        PostPullRequestCommentJob::dispatch($report->id);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
