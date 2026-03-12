<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\PostPullRequestCommentJob;
use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GitHubAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'app-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['coverage.github_app_webhook_secret' => $this->secret]);
    }

    private function postWebhook(string $payload, string $event = 'pull_request', ?string $signature = null): TestResponse
    {
        $sig = $signature ?? 'sha256='.hash_hmac('sha256', $payload, $this->secret);

        return $this->call('POST', '/webhooks/github-app', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
            'HTTP_X_GITHUB_EVENT' => $event,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $payload = json_encode(['action' => 'opened']);

        $response = $this->postWebhook($payload, 'pull_request', 'sha256=wrong-signature');

        $response->assertStatus(403);
    }

    public function test_valid_signature_returns_ok(): void
    {
        $payload = json_encode(['action' => 'something_else']);

        $response = $this->postWebhook($payload);

        $response->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_pull_request_opened_dispatches_job_when_report_found(): void
    {
        Queue::fake();

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
        $sha = str_repeat('a', 40);

        CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'commit_sha' => $sha,
            'status' => 'completed',
        ]);

        $payload = json_encode([
            'action' => 'opened',
            'pull_request' => ['head' => ['sha' => $sha]],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertPushed(PostPullRequestCommentJob::class);
    }

    public function test_pull_request_synchronize_dispatches_job(): void
    {
        Queue::fake();

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
        $sha = str_repeat('b', 40);

        CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'commit_sha' => $sha,
            'status' => 'completed',
        ]);

        $payload = json_encode([
            'action' => 'synchronize',
            'pull_request' => ['head' => ['sha' => $sha]],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertPushed(PostPullRequestCommentJob::class);
    }

    public function test_pull_request_reopened_dispatches_job(): void
    {
        Queue::fake();

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
        $sha = str_repeat('c', 40);

        CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'commit_sha' => $sha,
            'status' => 'completed',
        ]);

        $payload = json_encode([
            'action' => 'reopened',
            'pull_request' => ['head' => ['sha' => $sha]],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertPushed(PostPullRequestCommentJob::class);
    }

    public function test_no_job_dispatched_when_no_matching_report(): void
    {
        Queue::fake();

        Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        $payload = json_encode([
            'action' => 'opened',
            'pull_request' => ['head' => ['sha' => 'nonexistent-sha']],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertNotPushed(PostPullRequestCommentJob::class);
    }

    public function test_no_job_dispatched_when_repository_not_found(): void
    {
        Queue::fake();

        $payload = json_encode([
            'action' => 'opened',
            'pull_request' => ['head' => ['sha' => str_repeat('a', 40)]],
            'repository' => ['name' => 'unknown', 'owner' => ['login' => 'nobody']],
        ]);

        $this->postWebhook($payload);

        Queue::assertNotPushed(PostPullRequestCommentJob::class);
    }

    public function test_non_pull_request_event_does_not_dispatch_job(): void
    {
        Queue::fake();

        $payload = json_encode(['action' => 'push']);

        $this->postWebhook($payload, 'push');

        Queue::assertNotPushed(PostPullRequestCommentJob::class);
    }

    public function test_pull_request_closed_does_not_dispatch_job(): void
    {
        Queue::fake();

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
        $sha = str_repeat('d', 40);

        CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'commit_sha' => $sha,
            'status' => 'completed',
        ]);

        $payload = json_encode([
            'action' => 'closed',
            'pull_request' => ['head' => ['sha' => $sha]],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertNotPushed(PostPullRequestCommentJob::class);
    }

    public function test_only_completed_reports_trigger_job(): void
    {
        Queue::fake();

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
        $sha = str_repeat('e', 40);

        CoverageReport::factory()->pending()->create([
            'repository_id' => $repository->id,
            'commit_sha' => $sha,
        ]);

        $payload = json_encode([
            'action' => 'opened',
            'pull_request' => ['head' => ['sha' => $sha]],
            'repository' => ['name' => 'app', 'owner' => ['login' => 'acme']],
        ]);

        $this->postWebhook($payload);

        Queue::assertNotPushed(PostPullRequestCommentJob::class);
    }
}
