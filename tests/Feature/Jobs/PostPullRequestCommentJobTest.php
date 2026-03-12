<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PostPullRequestCommentJob;
use App\Models\CoverageReport;
use App\Models\PullRequestComment;
use App\Models\Repository;
use App\Services\GitHubAppService;
use App\Services\PullRequestCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PostPullRequestCommentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_early_when_not_configured(): void
    {
        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
            $mock->shouldNotReceive('getOpenPullRequestForBranch');
        });

        $report = CoverageReport::factory()->create();

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );
    }

    public function test_returns_early_when_report_not_found(): void
    {
        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldNotReceive('getOpenPullRequestForBranch');
        });

        (new PostPullRequestCommentJob(99999))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );
    }

    public function test_returns_early_when_no_open_pr(): void
    {
        $report = CoverageReport::factory()->create(['branch' => 'feature/x']);

        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getOpenPullRequestForBranch')
                ->once()
                ->andReturn(null);
        });

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );

        $this->assertDatabaseMissing('pull_request_comments', [
            'repository_id' => $report->repository_id,
        ]);
    }

    public function test_creates_new_comment_when_none_exists(): void
    {
        $repository = Repository::factory()->create(['default_branch' => 'main']);
        $report = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'feature/x',
            'status' => 'completed',
        ]);

        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getOpenPullRequestForBranch')->once()->andReturn(['number' => 42]);
            $mock->shouldReceive('getPullRequestFiles')->once()->andReturn(['app/Foo.php']);
            $mock->shouldReceive('createPullRequestComment')->once()->andReturn(123456);
            $mock->shouldNotReceive('updatePullRequestComment');
        });

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );

        $this->assertDatabaseHas('pull_request_comments', [
            'repository_id' => $repository->id,
            'pr_number' => 42,
            'github_comment_id' => 123456,
        ]);
    }

    public function test_updates_existing_comment_when_one_exists(): void
    {
        $repository = Repository::factory()->create(['default_branch' => 'main']);
        $report = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'feature/x',
            'status' => 'completed',
        ]);

        PullRequestComment::factory()->withGithubComment()->create([
            'repository_id' => $repository->id,
            'pr_number' => 42,
            'github_comment_id' => 999,
        ]);

        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getOpenPullRequestForBranch')->once()->andReturn(['number' => 42]);
            $mock->shouldReceive('getPullRequestFiles')->once()->andReturn([]);
            $mock->shouldReceive('updatePullRequestComment')->once()->with(\Mockery::any(), 999, \Mockery::any());
            $mock->shouldNotReceive('createPullRequestComment');
        });

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );
    }

    public function test_sets_pr_number_on_report_when_not_already_set(): void
    {
        $repository = Repository::factory()->create();
        $report = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'feature/x',
            'pr_number' => null,
        ]);

        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getOpenPullRequestForBranch')->once()->andReturn(['number' => 55]);
            $mock->shouldReceive('getPullRequestFiles')->once()->andReturn([]);
            $mock->shouldReceive('createPullRequestComment')->once()->andReturn(111);
        });

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );

        $this->assertEquals(55, $report->fresh()->pr_number);
    }

    public function test_does_not_overwrite_pr_number_when_already_set(): void
    {
        $repository = Repository::factory()->create();
        $report = CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => 'feature/x',
            'pr_number' => 42,
        ]);

        $githubAppService = $this->mock(GitHubAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getOpenPullRequestForBranch')->once()->andReturn(['number' => 42]);
            $mock->shouldReceive('getPullRequestFiles')->once()->andReturn([]);
            $mock->shouldReceive('createPullRequestComment')->once()->andReturn(111);
        });

        (new PostPullRequestCommentJob($report->id))->handle(
            $githubAppService,
            app(PullRequestCommentService::class)
        );

        $this->assertEquals(42, $report->fresh()->pr_number);
    }
}
