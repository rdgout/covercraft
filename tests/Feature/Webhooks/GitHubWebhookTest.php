<?php

namespace Tests\Feature\Webhooks;

use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_push_webhook_updates_cache(): void
    {
        Http::fake([
            '*/git/trees/*' => Http::response([
                'tree' => [
                    ['path' => 'src/App.php', 'type' => 'blob'],
                ],
            ]),
        ]);

        $secret = 'webhook-secret';
        $repository = Repository::factory()->create([
            'owner' => 'acme',
            'name' => 'app',
            'webhook_secret' => $secret,
        ]);

        $payload = json_encode([
            'ref' => 'refs/heads/main',
            'after' => str_repeat('a', 40),
            'repository' => [
                'name' => 'app',
                'owner' => ['login' => 'acme'],
            ],
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $response = $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'HTTP_X_GITHUB_EVENT' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('repository_file_cache', [
            'repository_id' => $repository->id,
            'branch' => 'main',
        ]);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'acme',
            'name' => 'app',
            'webhook_secret' => 'real-secret',
        ]);

        $payload = json_encode([
            'repository' => [
                'name' => 'app',
                'owner' => ['login' => 'acme'],
            ],
        ]);

        $response = $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong-signature',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(403);
    }

    public function test_unknown_repository_returns_404(): void
    {
        $payload = json_encode([
            'repository' => [
                'name' => 'unknown',
                'owner' => ['login' => 'nobody'],
            ],
        ]);

        $response = $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=something',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(404);
    }

    public function test_invalid_payload_returns_400(): void
    {
        $response = $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=something',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ], 'not json');

        $response->assertStatus(400);
    }

    public function test_non_push_event_returns_ok_without_processing(): void
    {
        $secret = 'webhook-secret';
        Repository::factory()->create([
            'owner' => 'acme',
            'name' => 'app',
            'webhook_secret' => $secret,
        ]);

        $payload = json_encode([
            'action' => 'opened',
            'repository' => [
                'name' => 'app',
                'owner' => ['login' => 'acme'],
            ],
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        Http::fake();

        $response = $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        Http::assertNothingSent();
    }
}
