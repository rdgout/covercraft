<?php

namespace App\Services;

use App\Contracts\GitHubServiceInterface;
use App\Exceptions\GitHubApiException;
use App\Models\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GitHubService implements GitHubServiceInterface
{
    /**
     * @return list<array{full_name: string, owner: string, name: string, default_branch: string}>
     */
    public function listUserRepositories(): array
    {
        $response = $this->githubClient()
            ->get($this->apiUrl('/user/repos'), [
                'per_page' => 100,
                'sort' => 'updated',
            ]);

        if ($response->failed()) {
            throw new GitHubApiException('Failed to fetch user repositories: '.$response->body());
        }

        return collect($response->json())
            ->map(fn (array $repo) => [
                'full_name' => $repo['full_name'],
                'owner' => $repo['owner']['login'],
                'name' => $repo['name'],
                'default_branch' => $repo['default_branch'],
            ])
            ->toArray();
    }

    /**
     * @return list<string>
     */
    public function listBranches(string $owner, string $name): array
    {
        $response = $this->githubClient()
            ->get($this->apiUrl("/repos/{$owner}/{$name}/branches"), [
                'per_page' => 100,
            ]);

        if ($response->failed()) {
            throw new GitHubApiException('Failed to fetch branches: '.$response->body());
        }

        return collect($response->json())
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return list<string>
     */
    public function fetchRepositoryFiles(Repository $repository, string $commitSha): array
    {
        $response = $this->githubClient()
            ->retry(3, 100, function (\Exception $exception) {
                return $exception instanceof RequestException
                    && $exception->response?->status() === 429;
            }, throw: false)
            ->get($this->apiUrl("/repos/{$repository->owner}/{$repository->name}/git/trees/{$commitSha}"), [
                'recursive' => 1,
            ]);

        if ($response->failed()) {
            throw new GitHubApiException(
                "Failed to fetch repository files: HTTP {$response->status()}"
            );
        }

        return collect($response->json()['tree'] ?? [])
            ->filter(fn (array $item) => $item['type'] === 'blob')
            ->pluck('path')
            ->toArray();
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Fetch the contents of a file from GitHub.
     */
    public function fetchFileContents(Repository $repository, string $commitSha, string $filePath): string
    {
        $response = $this->githubClient()
            ->retry(3, 100, function (\Exception $exception) {
                return $exception instanceof RequestException
                    && $exception->response?->status() === 429;
            }, throw: false)
            ->get($this->apiUrl("/repos/{$repository->owner}/{$repository->name}/contents/{$filePath}"), [
                'ref' => $commitSha,
            ]);

        if ($response->failed()) {
            throw new GitHubApiException(
                "Failed to fetch file contents: HTTP {$response->status()}"
            );
        }

        $data = $response->json();

        // GitHub returns base64-encoded content
        if (isset($data['content'])) {
            return base64_decode(str_replace("\n", '', $data['content']));
        }

        throw new GitHubApiException('File content not found in response');
    }

    private function githubClient(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('coverage.github_token'),
            'Accept' => 'application/vnd.github.v3+json',
        ]);
    }

    private function apiUrl(string $path): string
    {
        return config('coverage.github_api_url').$path;
    }
}
