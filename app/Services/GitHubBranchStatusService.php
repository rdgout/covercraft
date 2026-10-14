<?php

namespace App\Services;

use App\Models\CoverageReport;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GitHubBranchStatusService
{
    public function __construct(private GitHubAppService $githubApp) {}

    public function isConfigured(): bool
    {
        return $this->githubApp->isConfigured() || filled(config('coverage.github_token'));
    }

    /**
     * @param  Collection<int, CoverageReport>  $latestReports
     * @return list<string>
     */
    public function mergedBranches(Repository $repository, Collection $latestReports, CarbonInterface $mergedBefore): array
    {
        if ($latestReports->isEmpty() || ! $this->isConfigured()) {
            return [];
        }

        $token = $this->githubApp->isConfigured()
            ? $this->githubApp->getInstallationToken($repository)
            : config('coverage.github_token');

        $client = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->connectTimeout(5)
            ->timeout(15);

        $pulls = $this->pullRequests($client, $repository);
        $mergedReports = collect();

        foreach ($latestReports as $report) {
            $branchPulls = $pulls->filter(fn (array $pull): bool => data_get($pull, 'head.ref') === $report->branch
                && strcasecmp(data_get($pull, 'head.repo.full_name', ''), "{$repository->owner}/{$repository->name}") === 0
            );

            if ($branchPulls->contains(fn (array $pull): bool => ($pull['state'] ?? null) === 'open')) {
                continue;
            }

            $mergedPull = $branchPulls->first(fn (array $pull): bool => filled($pull['merged_at'] ?? null));

            if (! $mergedPull || data_get($mergedPull, 'head.sha') !== $report->commit_sha) {
                continue;
            }

            $mergedAt = CarbonImmutable::parse($mergedPull['merged_at']);

            if ($mergedAt->greaterThan($mergedBefore) || $report->created_at->greaterThan($mergedAt)) {
                continue;
            }

            $mergedReports->push($report);
        }

        if ($mergedReports->isEmpty()) {
            return [];
        }

        $heads = $this->items($client, $repository, '/branches')->mapWithKeys(function (array $branch): array {
            if (! is_string($branch['name'] ?? null) || ! is_string(data_get($branch, 'commit.sha'))) {
                throw new UnexpectedValueException('GitHub returned an invalid branch.');
            }

            return [$branch['name'] => $branch['commit']['sha']];
        });

        return $mergedReports
            ->filter(fn (CoverageReport $report): bool => ! $heads->has($report->branch) || $heads->get($report->branch) === $report->commit_sha)
            ->pluck('branch')
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pullRequests(PendingRequest $client, Repository $repository): Collection
    {
        $query = <<<'GRAPHQL'
            query CoverageCleanup($owner: String!, $name: String!, $cursor: String) {
                repository(owner: $owner, name: $name) {
                    pullRequests(first: 100, after: $cursor, states: [OPEN, CLOSED, MERGED], orderBy: {field: UPDATED_AT, direction: DESC}) {
                        nodes {
                            state
                            mergedAt
                            headRefName
                            headRefOid
                            headRepository { nameWithOwner }
                        }
                        pageInfo { hasNextPage endCursor }
                    }
                }
            }
            GRAPHQL;

        $pulls = collect();
        $cursor = null;

        do {
            $response = $client->post(config('coverage.github_graphql_url'), [
                'query' => $query,
                'variables' => ['owner' => $repository->owner, 'name' => $repository->name, 'cursor' => $cursor],
            ])->throw();

            $connection = $response->json('data.repository.pullRequests');
            $nodes = data_get($connection, 'nodes');
            $hasNextPage = data_get($connection, 'pageInfo.hasNextPage');
            $nextCursor = data_get($connection, 'pageInfo.endCursor');

            if ($response->json('errors') || ! is_array($nodes) || ! array_is_list($nodes) || ! is_bool($hasNextPage)
                || ($hasNextPage && (! is_string($nextCursor) || $nextCursor === $cursor))) {
                throw new UnexpectedValueException('GitHub returned incomplete pull request data.');
            }

            $pulls = $pulls->concat(collect($nodes)->map(fn (array $pull): array => [
                'state' => strtolower($pull['state']),
                'merged_at' => $pull['mergedAt'],
                'head' => [
                    'ref' => $pull['headRefName'],
                    'sha' => $pull['headRefOid'],
                    'repo' => ['full_name' => data_get($pull, 'headRepository.nameWithOwner', '')],
                ],
            ]));

            $cursor = $nextCursor;
        } while ($hasNextPage);

        return $pulls->sortByDesc('merged_at')->values();
    }

    /**
     * @param  array<string, string>  $parameters
     * @return Collection<int, array<string, mixed>>
     */
    private function items(PendingRequest $client, Repository $repository, string $path, array $parameters = []): Collection
    {
        $items = collect();
        $page = 1;

        do {
            $response = $client->get($this->apiUrl($repository, $path), array_merge($parameters, [
                'per_page' => 100,
                'page' => $page,
            ]))->throw();

            $pageItems = $response->json();

            if (! is_array($pageItems) || ! array_is_list($pageItems)) {
                throw new UnexpectedValueException('GitHub returned an invalid list.');
            }

            $items = $items->concat($pageItems);
            $page++;
        } while (str_contains($response->header('Link'), 'rel="next"'));

        return $items;
    }

    private function apiUrl(Repository $repository, string $path): string
    {
        return rtrim(config('coverage.github_api_url'), '/')."/repos/{$repository->owner}/{$repository->name}{$path}";
    }
}
