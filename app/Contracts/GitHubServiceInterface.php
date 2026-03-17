<?php

namespace App\Contracts;

use App\Models\Repository;

interface GitHubServiceInterface
{
    /**
     * @return list<array{full_name: string, owner: string, name: string, default_branch: string}>
     */
    public function listUserRepositories(): array;

    /**
     * @return list<string>
     */
    public function listBranches(string $owner, string $name): array;

    /**
     * @return list<string>
     */
    public function fetchRepositoryFiles(Repository $repository, string $commitSha): array;

    /**
     * @return list<string>
     */
    public function getOrFetchRepositoryFiles(Repository $repository, string $branch, string $commitSha): array;

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;

    public function handlePushWebhook(array $payload): void;

    public function fetchFileContents(Repository $repository, string $commitSha, string $filePath): string;
}
