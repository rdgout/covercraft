<?php

namespace Tests\Feature\Console;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Services\GitHubAppService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CleanupCoverageReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfSecond());
        config([
            'coverage.github_app_id' => null,
            'coverage.github_app_private_key' => null,
            'coverage.github_token' => 'test-token',
            'coverage.github_api_url' => 'https://api.github.com',
            'coverage.github_graphql_url' => 'https://api.github.com/graphql',
            'coverage.storage_disk' => 'local',
            'coverage.retention.default_branch_reports' => 30,
            'coverage.retention.failed_report_days' => 7,
            'coverage.retention.merged_branch_days' => 7,
        ]);
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    public function test_keeps_the_latest_thirty_successful_default_branch_reports(): void
    {
        $repository = Repository::factory()->withDefaultBranch('acceptance')->create();
        $reports = collect();

        for ($index = 0; $index < 36; $index++) {
            $reports->push($this->report($repository, 'acceptance', 36 - $index, ['archived' => $index < 35]));
        }

        $this->artisan('coverage:cleanup')->expectsOutput('Removed 6 reports and 0 coverage files.')->assertSuccessful();

        $this->assertEqualsCanonicalizing($reports->slice(6)->pluck('id')->all(), $repository->coverageReports()->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_preserves_latest_successful_reports_failures_and_in_flight_reports(): void
    {
        Http::fake(['*/graphql' => Http::response($this->pullResponse([]))]);
        $repository = Repository::factory()->create();
        $oldSuccess = $this->report($repository, 'feature/failing', 91, ['archived' => true]);
        $lastSuccess = $this->report($repository, 'feature/failing', 90, ['archived' => true]);
        $oldFailure = $this->report($repository, 'feature/failing', 10, ['archived' => true, 'status' => 'failed']);
        $recentFailure = $this->report($repository, 'feature/failing', 3, ['archived' => true, 'status' => 'failed']);
        $latestFailure = $this->report($repository, 'feature/failing', 2, ['status' => 'failed']);
        $pending = $this->report($repository, 'feature/pending', 100, ['archived' => true, 'status' => 'pending']);
        $processing = $this->report($repository, 'feature/pending', 101, ['archived' => true, 'status' => 'processing']);
        $inactive = $this->report($repository, 'feature/no-pr', 200);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertModelMissing($oldSuccess);
        $this->assertModelMissing($oldFailure);

        foreach ([$lastSuccess, $recentFailure, $latestFailure, $pending, $processing, $inactive] as $report) {
            $this->assertModelExists($report);
        }
    }

    public function test_dry_run_leaves_reports_coverage_files_and_clover_files_untouched(): void
    {
        Http::fake(['*/graphql' => Http::response($this->pullResponse([]))]);
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'feature/open', 20, ['archived' => true]);
        $latest = $this->report($repository, 'feature/open', 10);
        $file = CoverageFile::factory()->for($old)->create();
        Storage::disk('local')->put($old->clover_file_path, '<coverage/>');

        $this->artisan('coverage:cleanup', ['--dry-run' => true])
            ->expectsOutput('Would remove 1 reports and 1 coverage files.')
            ->assertSuccessful();

        $this->assertModelExists($old);
        $this->assertModelExists($latest);
        $this->assertModelExists($file);
        Storage::disk('local')->assertExists($old->clover_file_path);
    }

    public function test_deletes_coverage_rows_and_unreferenced_clover_files_but_preserves_shared_paths(): void
    {
        Http::fake(['*/graphql' => Http::response($this->pullResponse([]))]);
        $repository = Repository::factory()->create();
        $exclusive = $this->report($repository, 'feature/open', 30, ['archived' => true]);
        $shared = $this->report($repository, 'feature/open', 20, ['archived' => true]);
        $missing = $this->report($repository, 'feature/open', 15, ['archived' => true]);
        $latest = $this->report($repository, 'feature/open', 10, ['clover_file_path' => $shared->clover_file_path]);
        $file = CoverageFile::factory()->for($exclusive)->create();
        Storage::disk('local')->put($exclusive->clover_file_path, '<coverage/>');
        Storage::disk('local')->put($shared->clover_file_path, '<coverage/>');

        $this->artisan('coverage:cleanup')->expectsOutput('Removed 3 reports and 1 coverage files.')->assertSuccessful();

        foreach ([$exclusive, $shared, $missing, $file] as $model) {
            $this->assertModelMissing($model);
        }

        $this->assertModelExists($latest);
        Storage::disk('local')->assertMissing($exclusive->clover_file_path);
        Storage::disk('local')->assertExists($shared->clover_file_path);
    }

    public function test_removes_confirmed_merged_branches_after_the_grace_period_and_cleans_their_cache(): void
    {
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'feature/merged', 30, ['archived' => true]);
        $latest = $this->report($repository, 'feature/merged', 20);
        $cache = RepositoryFileCache::factory()->for($repository)->create(['branch' => $latest->branch]);
        $unrelated = $this->report(Repository::factory()->create(), 'feature/merged', 20);
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $latest)])),
            '*/branches*' => Http::response([]),
        ]);

        $this->artisan('coverage:cleanup', ['--repository' => $repository->id])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelMissing($latest);
        $this->assertModelMissing($cache);
        $this->assertModelExists($unrelated);
    }

    public function test_dry_run_does_not_remove_a_confirmed_merged_branch_or_its_cache(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        $cache = RepositoryFileCache::factory()->for($repository)->create(['branch' => $report->branch]);
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $report)])),
            '*/branches*' => Http::response([['name' => $report->branch, 'commit' => ['sha' => $report->commit_sha]]]),
        ]);

        $this->artisan('coverage:cleanup', ['--dry-run' => true])
            ->expectsOutput('Would remove 1 reports and 0 coverage files.')->assertSuccessful();

        $this->assertModelExists($report);
        $this->assertModelExists($cache);
    }

    public function test_preserves_unmerged_open_reused_recently_merged_and_unknown_branches(): void
    {
        $repository = Repository::factory()->create();
        $reports = collect();
        $pulls = [];

        foreach (['unmerged', 'open', 'new-report-sha', 'new-remote-sha', 'fork', 'recent-merge', 'post-merge-upload', 'unknown', 'in-flight'] as $branch) {
            $report = $this->report($repository, "feature/{$branch}", 20);
            $reports->push($report);
            $pull = $this->pull($repository, $report);

            if ($branch === 'unmerged') {
                $pull['merged_at'] = null;
            } elseif ($branch === 'open') {
                $pulls[] = $pull;
                $pull['state'] = 'open';
                $pull['merged_at'] = null;
            } elseif ($branch === 'new-report-sha') {
                $pull['head']['sha'] = 'a-different-commit';
            } elseif ($branch === 'fork') {
                $pull['head']['repo']['full_name'] = 'someone/else';
            } elseif ($branch === 'recent-merge') {
                $pull['merged_at'] = now()->subDays(2)->toIso8601String();
            } elseif ($branch === 'post-merge-upload') {
                $pull['merged_at'] = now()->subDays(30)->toIso8601String();
            } elseif ($branch === 'unknown') {
                continue;
            } elseif ($branch === 'in-flight') {
                $reports->push($this->report($repository, $report->branch, 30, ['archived' => true, 'status' => 'processing']));
            }

            $pulls[] = $pull;
        }

        Http::fake([
            '*/graphql' => Http::response($this->pullResponse($pulls)),
            '*/branches*' => Http::response([['name' => 'feature/new-remote-sha', 'commit' => ['sha' => 'a-new-remote-commit']]]),
        ]);

        $this->artisan('coverage:cleanup')->expectsOutput('Removed 0 reports and 0 coverage files.')->assertSuccessful();

        foreach ($reports as $report) {
            $this->assertModelExists($report);
        }
    }

    public function test_fetches_every_pull_request_page_before_deciding_whether_a_branch_was_merged(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/reused', 20);
        $merged = $this->pull($repository, $report);
        $open = array_replace($merged, ['state' => 'open', 'merged_at' => null]);
        Http::fake([
            '*/graphql' => Http::sequence()
                ->push($this->pullResponse([$merged], 'next-page'))
                ->push($this->pullResponse([$open])),
        ]);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertModelExists($report);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['variables']['cursor'] === 'next-page' && $request['variables']['owner'] === $repository->owner);
    }

    public function test_github_failure_keeps_latest_branch_reports_while_pruning_superseded_history(): void
    {
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'feature/unknown', 30, ['archived' => true]);
        $latest = $this->report($repository, 'feature/unknown', 20);
        Http::fake(['*/graphql' => Http::response([], 500)]);

        $this->artisan('coverage:cleanup')
            ->expectsOutputToContain('Could not verify merges')
            ->assertFailed();

        $this->assertModelMissing($old);
        $this->assertModelExists($latest);
    }

    public function test_checks_every_branch_page_to_protect_a_branch_that_has_advanced(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/reused', 20);
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $report)])),
            '*/branches*' => Http::sequence()
                ->push([['name' => 'main', 'commit' => ['sha' => 'main-sha']]], 200, ['Link' => '<https://api.github.com/branches?page=2>; rel="next"'])
                ->push([['name' => $report->branch, 'commit' => ['sha' => 'new-sha']]]),
        ]);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertModelExists($report);
        Http::assertSentCount(3);
    }

    public function test_an_invalid_branch_response_preserves_reports(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $report)])),
            '*/branches*' => Http::response(['unexpected' => 'response']),
        ]);

        $this->artisan('coverage:cleanup')->assertFailed();

        $this->assertModelExists($report);
    }

    public function test_branch_permission_errors_do_not_count_as_a_deleted_branch(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $report)])),
            '*/branches*' => Http::response([], 403),
        ]);

        $this->artisan('coverage:cleanup')->assertFailed();

        $this->assertModelExists($report);
    }

    public function test_partial_graphql_results_do_not_allow_branch_deletion(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        $response = $this->pullResponse([$this->pull($repository, $report)]);
        $response['errors'] = [['message' => 'Permission denied']];
        Http::fake(['*/graphql' => Http::response($response)]);

        $this->artisan('coverage:cleanup')->assertFailed();

        $this->assertModelExists($report);
        Http::assertSentCount(1);
    }

    public function test_incomplete_pagination_does_not_allow_branch_deletion(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        Http::fake([
            '*/graphql' => Http::sequence()
                ->push($this->pullResponse([$this->pull($repository, $report)], 'same-cursor'))
                ->push($this->pullResponse([], 'same-cursor')),
        ]);

        $this->artisan('coverage:cleanup')->assertFailed();

        $this->assertModelExists($report);
        Http::assertSentCount(2);
    }

    public function test_a_new_upload_during_github_verification_protects_the_reused_branch(): void
    {
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'feature/reused', 20);
        $new = null;
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $old)])),
            '*/branches*' => function () use ($repository, $old, &$new) {
                $old->update(['archived' => true]);
                $new = $this->report($repository, $old->branch, 0);

                return Http::response([['name' => $old->branch, 'commit' => ['sha' => $old->commit_sha]]]);
            },
        ]);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertNotNull($new);
        $this->assertModelExists($new);
    }

    public function test_uses_the_github_app_installation_token_when_configured(): void
    {
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/merged', 20);
        $this->mock(GitHubAppService::class, function ($mock) use ($repository): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getInstallationToken')->once()->withArgs(fn (Repository $repo): bool => $repo->is($repository))->andReturn('installation-token');
        });
        Http::fake([
            '*/graphql' => Http::response($this->pullResponse([$this->pull($repository, $report)])),
            '*/branches*' => Http::response([]),
        ]);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertModelMissing($report);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer installation-token'));
    }

    public function test_missing_github_credentials_does_not_remove_latest_branch_reports(): void
    {
        config(['coverage.github_token' => null]);
        $repository = Repository::factory()->create();
        $report = $this->report($repository, 'feature/unknown', 200);

        $this->artisan('coverage:cleanup')->expectsOutputToContain('GitHub is not configured')->assertSuccessful();

        $this->assertModelExists($report);
        Http::assertNothingSent();
    }

    public function test_storage_deletion_failure_preserves_the_report_for_retry(): void
    {
        Http::fake(['*/graphql' => Http::response($this->pullResponse([]))]);
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'feature/open', 20, ['archived' => true]);
        $this->report($repository, 'feature/open', 10);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->with($old->clover_file_path)->once()->andReturn(true);
        $disk->shouldReceive('delete')->with($old->clover_file_path)->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->artisan('coverage:cleanup')->expectsOutputToContain("Could not remove report {$old->id}")->assertFailed();

        $this->assertModelExists($old);
    }

    public function test_configured_retention_and_tied_timestamps_use_report_id_order(): void
    {
        config(['coverage.retention.default_branch_reports' => 2]);
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'main', 20, ['archived' => true]);
        $middle = $this->report($repository, 'main', 20, ['archived' => true]);
        $latest = $this->report($repository, 'main', 20);

        $this->artisan('coverage:cleanup')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($middle);
        $this->assertModelExists($latest);
    }

    public function test_running_cleanup_again_has_nothing_left_to_remove(): void
    {
        $repository = Repository::factory()->create();
        $old = $this->report($repository, 'main', 20, ['archived' => true]);
        $latest = $this->report($repository, 'main', 10);
        config(['coverage.retention.default_branch_reports' => 1]);

        $this->artisan('coverage:cleanup')->assertSuccessful();
        $this->artisan('coverage:cleanup')->expectsOutput('Removed 0 reports and 0 coverage files.')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($latest);
    }

    #[DataProvider('invalidRetention')]
    public function test_invalid_retention_is_rejected_without_deleting_reports(string $setting, mixed $value): void
    {
        config(["coverage.retention.{$setting}" => $value]);
        $report = CoverageReport::factory()->create();

        $this->artisan('coverage:cleanup')->assertFailed();

        $this->assertModelExists($report);
        Http::assertNothingSent();
    }

    /**
     * @return list<array{string, mixed}>
     */
    public static function invalidRetention(): array
    {
        return [
            ['default_branch_reports', 0],
            ['failed_report_days', -1],
            ['merged_branch_days', 'invalid'],
        ];
    }

    public function test_unknown_and_invalid_repository_ids_are_rejected(): void
    {
        $this->artisan('coverage:cleanup', ['--repository' => 999])->expectsOutput('Repository not found.')->assertFailed();
        $this->artisan('coverage:cleanup', ['--repository' => 'not-an-id'])->expectsOutput('The repository option must be a repository ID.')->assertFailed();
    }

    public function test_cleanup_is_scheduled_daily_with_overlap_and_single_server_protection(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'coverage:cleanup'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(Repository $repository, string $branch, int $daysAgo, array $attributes = []): CoverageReport
    {
        return CoverageReport::factory()->for($repository)->onBranch($branch)->create(array_merge([
            'created_at' => now()->subDays($daysAgo),
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function pull(Repository $repository, CoverageReport $report): array
    {
        return [
            'state' => 'closed',
            'merged_at' => now()->subDays(10)->toIso8601String(),
            'head' => [
                'ref' => $report->branch,
                'sha' => $report->commit_sha,
                'repo' => ['full_name' => "{$repository->owner}/{$repository->name}"],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pulls
     * @return array<string, mixed>
     */
    private function pullResponse(array $pulls, ?string $nextCursor = null): array
    {
        return ['data' => ['repository' => ['pullRequests' => [
            'nodes' => array_map(fn (array $pull): array => [
                'state' => strtoupper($pull['state']),
                'mergedAt' => $pull['merged_at'],
                'headRefName' => $pull['head']['ref'],
                'headRefOid' => $pull['head']['sha'],
                'headRepository' => ['nameWithOwner' => $pull['head']['repo']['full_name']],
            ], $pulls),
            'pageInfo' => ['hasNextPage' => $nextCursor !== null, 'endCursor' => $nextCursor],
        ]]]];
    }
}
