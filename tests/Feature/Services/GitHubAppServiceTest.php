<?php

namespace Tests\Feature\Services;

use App\Models\Repository;
use App\Services\GitHubAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private GitHubAppService $service;

    private string $privatePem = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GitHubAppService;

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $pem = '';
        openssl_pkey_export($key, $pem);
        $this->privatePem = $pem;
    }

    public function test_is_configured_returns_false_when_no_app_id(): void
    {
        config(['coverage.github_app_id' => null, 'coverage.github_app_private_key' => 'key']);

        $this->assertFalse($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_no_private_key(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => null]);

        $this->assertFalse($this->service->isConfigured());
    }

    public function test_is_configured_returns_true_when_both_set(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $this->assertTrue($this->service->isConfigured());
    }

    public function test_generate_jwt_produces_three_segment_token(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $jwt = $this->service->generateJwt();

        $this->assertCount(3, explode('.', $jwt));
    }

    public function test_generate_jwt_header_is_rs256(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $jwt = $this->service->generateJwt();
        $header = json_decode(base64_decode(str_pad(strtr(explode('.', $jwt)[0], '-_', '+/'), strlen(explode('.', $jwt)[0]) % 4 === 0 ? strlen(explode('.', $jwt)[0]) : strlen(explode('.', $jwt)[0]) + 4 - strlen(explode('.', $jwt)[0]) % 4, '=')), true);

        $this->assertEquals('RS256', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
    }

    public function test_generate_jwt_handles_escaped_newlines_in_pem(): void
    {
        $escapedPem = str_replace("\n", '\\n', $this->privatePem);
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $escapedPem]);

        $jwt = $this->service->generateJwt();

        $this->assertCount(3, explode('.', $jwt));
    }

    public function test_get_installation_token_returns_token(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_test_token']),
        ]);

        $token = $this->service->getInstallationToken($repository);

        $this->assertEquals('ghs_test_token', $token);
    }

    public function test_get_installation_token_is_cached(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_cached_token']),
        ]);

        $this->service->getInstallationToken($repository);
        $this->service->getInstallationToken($repository);

        Http::assertSentCount(2); // installation + access_tokens only called once total
    }

    public function test_get_open_pull_request_for_branch_returns_null_when_not_configured(): void
    {
        config(['coverage.github_app_id' => null]);

        $repository = Repository::factory()->create();

        $result = $this->service->getOpenPullRequestForBranch($repository, 'feature/x');

        $this->assertNull($result);
    }

    public function test_get_open_pull_request_for_branch_returns_first_pr(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_token']),
            '*/repos/acme/app/pulls*' => Http::response([
                ['number' => 42, 'title' => 'My PR'],
            ]),
        ]);

        $pr = $this->service->getOpenPullRequestForBranch($repository, 'feature/x');

        $this->assertEquals(42, $pr['number']);
    }

    public function test_get_open_pull_request_for_branch_returns_null_when_empty(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_token']),
            '*/repos/acme/app/pulls*' => Http::response([]),
        ]);

        $pr = $this->service->getOpenPullRequestForBranch($repository, 'feature/no-pr');

        $this->assertNull($pr);
    }

    public function test_get_pull_request_files_returns_filenames(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_token']),
            '*/repos/acme/app/pulls/42/files' => Http::response([
                ['filename' => 'app/Foo.php'],
                ['filename' => 'app/Bar.php'],
            ]),
        ]);

        $files = $this->service->getPullRequestFiles($repository, 42);

        $this->assertEquals(['app/Foo.php', 'app/Bar.php'], $files);
    }

    public function test_get_pull_request_files_returns_empty_when_not_configured(): void
    {
        config(['coverage.github_app_id' => null]);

        $repository = Repository::factory()->create();

        $files = $this->service->getPullRequestFiles($repository, 42);

        $this->assertEmpty($files);
    }

    public function test_create_pull_request_comment_returns_comment_id(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_token']),
            '*/repos/acme/app/issues/42/comments' => Http::response(['id' => 123456]),
        ]);

        $commentId = $this->service->createPullRequestComment($repository, 42, 'Hello PR!');

        $this->assertEquals(123456, $commentId);
    }

    public function test_update_pull_request_comment_sends_patch(): void
    {
        config(['coverage.github_app_id' => '12345', 'coverage.github_app_private_key' => $this->privatePem]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        Http::fake([
            '*/repos/acme/app/installation' => Http::response(['id' => 999]),
            '*/app/installations/999/access_tokens' => Http::response(['token' => 'ghs_token']),
            '*/repos/acme/app/issues/comments/123456' => Http::response(['id' => 123456]),
        ]);

        $this->service->updatePullRequestComment($repository, 123456, 'Updated body');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'issues/comments/123456');
        });
    }

    public function test_verify_webhook_signature_returns_true_for_valid_signature(): void
    {
        $secret = 'my-webhook-secret';
        config(['coverage.github_app_webhook_secret' => $secret]);

        $payload = '{"action":"opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->service->verifyWebhookSignature($payload, $signature));
    }

    public function test_verify_webhook_signature_returns_false_for_invalid_signature(): void
    {
        config(['coverage.github_app_webhook_secret' => 'real-secret']);

        $this->assertFalse($this->service->verifyWebhookSignature('payload', 'sha256=wrong'));
    }

    public function test_verify_webhook_signature_returns_false_when_no_secret_configured(): void
    {
        config(['coverage.github_app_webhook_secret' => null]);

        $this->assertFalse($this->service->verifyWebhookSignature('payload', 'sha256=anything'));
    }
}
