<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function github(Request $request, GitHubService $githubService): JsonResponse
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

        if ($event === 'push') {
            $githubService->handlePushWebhook($data);
        }

        return response()->json(['status' => 'ok']);
    }
}
