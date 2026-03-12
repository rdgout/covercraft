<?php

namespace App\Services;

use App\Models\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GitHubAppService
{
    public function isConfigured(): bool
    {
        return ! empty(config('coverage.github_app_id'))
            && ! empty(config('coverage.github_app_private_key'));
    }

    public function generateJwt(): string
    {
        $pem = str_replace('\\n', "\n", config('coverage.github_app_private_key'));
        $key = openssl_pkey_get_private($pem);
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => config('coverage.github_app_id'),
            'iat' => $now - 60,
            'exp' => $now + 600,
        ]));

        openssl_sign("$header.$payload", $signature, $key, OPENSSL_ALGO_SHA256);

        return "$header.$payload.".$this->base64UrlEncode($signature);
    }

    public function getInstallationToken(Repository $repo): string
    {
        $cacheKey = "github_app_token_{$repo->owner}_{$repo->name}";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($repo): string {
            $jwt = $this->generateJwt();

            $installationResponse = Http::withHeaders([
                'Authorization' => "Bearer $jwt",
                'Accept' => 'application/vnd.github.v3+json',
            ])->get($this->apiUrl("/repos/{$repo->owner}/{$repo->name}/installation"));

            $installationId = $installationResponse->json('id');

            $tokenResponse = Http::withHeaders([
                'Authorization' => "Bearer $jwt",
                'Accept' => 'application/vnd.github.v3+json',
            ])->post($this->apiUrl("/app/installations/{$installationId}/access_tokens"));

            return $tokenResponse->json('token');
        });
    }

    public function getOpenPullRequestForBranch(Repository $repo, string $branch): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $token = $this->getInstallationToken($repo);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($this->apiUrl("/repos/{$repo->owner}/{$repo->name}/pulls"), [
            'head' => "{$repo->owner}:{$branch}",
            'state' => 'open',
        ]);

        $pulls = $response->json();

        if (empty($pulls)) {
            return null;
        }

        return $pulls[0];
    }

    /**
     * @return list<string>
     */
    public function getPullRequestFiles(Repository $repo, int $prNumber): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $token = $this->getInstallationToken($repo);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($this->apiUrl("/repos/{$repo->owner}/{$repo->name}/pulls/{$prNumber}/files"));

        return collect($response->json())
            ->pluck('filename')
            ->toArray();
    }

    public function createPullRequestComment(Repository $repo, int $prNumber, string $body): int
    {
        $token = $this->getInstallationToken($repo);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($this->apiUrl("/repos/{$repo->owner}/{$repo->name}/issues/{$prNumber}/comments"), [
            'body' => $body,
        ]);

        return $response->json('id');
    }

    public function updatePullRequestComment(Repository $repo, int $commentId, string $body): void
    {
        $token = $this->getInstallationToken($repo);

        Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/vnd.github.v3+json',
        ])->patch($this->apiUrl("/repos/{$repo->owner}/{$repo->name}/issues/comments/{$commentId}"), [
            'body' => $body,
        ]);
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('coverage.github_app_webhook_secret');

        if (empty($secret)) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function apiUrl(string $path): string
    {
        return config('coverage.github_api_url').$path;
    }
}
