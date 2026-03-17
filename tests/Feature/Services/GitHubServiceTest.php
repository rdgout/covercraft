<?php

namespace Tests\Feature\Services;

use App\Exceptions\GitHubApiException;
use App\Models\Repository;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceTest extends TestCase
{
    use RefreshDatabase;

    private GitHubService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GitHubService;
    }

    public function test_list_user_repositories(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response([
                [
                    'full_name' => 'acme/app',
                    'name' => 'app',
                    'owner' => ['login' => 'acme'],
                    'default_branch' => 'main',
                ],
            ]),
        ]);

        $repos = $this->service->listUserRepositories();

        $this->assertCount(1, $repos);
        $this->assertEquals('acme/app', $repos[0]['full_name']);
        $this->assertEquals('acme', $repos[0]['owner']);
        $this->assertEquals('app', $repos[0]['name']);
        $this->assertEquals('main', $repos[0]['default_branch']);
    }

    public function test_list_user_repositories_sends_auth_header(): void
    {
        config(['coverage.github_token' => 'test-token-123']);

        Http::fake([
            '*/user/repos*' => Http::response([]),
        ]);

        $this->service->listUserRepositories();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-token-123');
        });
    }

    public function test_list_branches(): void
    {
        Http::fake([
            '*/repos/acme/app/branches*' => Http::response([
                ['name' => 'main'],
                ['name' => 'develop'],
                ['name' => 'feature/login'],
            ]),
        ]);

        $branches = $this->service->listBranches('acme', 'app');

        $this->assertEquals(['main', 'develop', 'feature/login'], $branches);
    }

    public function test_fetch_repository_files_filters_blobs(): void
    {
        Http::fake([
            '*/git/trees/*' => Http::response([
                'tree' => [
                    ['path' => 'src/Foo.php', 'type' => 'blob'],
                    ['path' => 'src', 'type' => 'tree'],
                    ['path' => 'src/Bar.php', 'type' => 'blob'],
                ],
            ]),
        ]);

        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        $files = $this->service->fetchRepositoryFiles($repository, 'abc123');

        $this->assertEquals(['src/Foo.php', 'src/Bar.php'], $files);
    }

    public function test_fetch_repository_files_throws_on_failure(): void
    {
        Http::fake([
            '*/git/trees/*' => Http::response('Not found', 404),
        ]);

        $repository = Repository::factory()->create();

        $this->expectException(GitHubApiException::class);

        $this->service->fetchRepositoryFiles($repository, 'abc123');
    }

    public function test_verify_webhook_signature_valid(): void
    {
        $payload = '{"action":"push"}';
        $secret = 'my-secret';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->service->verifyWebhookSignature($payload, $signature, $secret));
    }

    public function test_verify_webhook_signature_invalid(): void
    {
        $this->assertFalse($this->service->verifyWebhookSignature('payload', 'sha256=wrong', 'secret'));
    }

    public function test_list_user_repositories_throws_on_failure(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(GitHubApiException::class);

        $this->service->listUserRepositories();
    }

    public function test_list_branches_throws_on_failure(): void
    {
        Http::fake([
            '*/branches*' => Http::response('Not found', 404),
        ]);

        $this->expectException(GitHubApiException::class);

        $this->service->listBranches('acme', 'nonexistent');
    }

    public function test_fetch_file_contents(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'acme',
            'name' => 'app',
        ]);

        $fileContent = "<?php\n\nnamespace App;\n\nclass Foo\n{\n    //\n}\n";
        $encodedContent = base64_encode($fileContent);

        Http::fake([
            '*/repos/acme/app/contents/src/Foo.php*' => Http::response([
                'content' => $encodedContent,
                'encoding' => 'base64',
            ]),
        ]);

        $contents = $this->service->fetchFileContents($repository, 'abc123', 'src/Foo.php');

        $this->assertEquals($fileContent, $contents);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ref=abc123'));
    }

    public function test_fetch_file_contents_handles_newlines_in_base64(): void
    {
        $repository = Repository::factory()->create();

        $fileContent = str_repeat('x', 100);
        // GitHub adds newlines every 60 chars in base64
        $encodedContent = chunk_split(base64_encode($fileContent), 60, "\n");

        Http::fake([
            '*/contents/*' => Http::response([
                'content' => $encodedContent,
                'encoding' => 'base64',
            ]),
        ]);

        $contents = $this->service->fetchFileContents($repository, 'abc123', 'file.txt');

        $this->assertEquals($fileContent, $contents);
    }

    public function test_fetch_file_contents_throws_on_missing_content(): void
    {
        $repository = Repository::factory()->create();

        Http::fake([
            '*/contents/*' => Http::response([
                'message' => 'Not Found',
            ]),
        ]);

        $this->expectException(GitHubApiException::class);
        $this->expectExceptionMessage('File content not found in response');

        $this->service->fetchFileContents($repository, 'abc123', 'missing.php');
    }

    public function test_fetch_file_contents_throws_on_api_error(): void
    {
        $repository = Repository::factory()->create();

        Http::fake([
            '*/contents/*' => Http::response([], 404),
        ]);

        $this->expectException(GitHubApiException::class);
        $this->expectExceptionMessage('Failed to fetch file contents: HTTP 404');

        $this->service->fetchFileContents($repository, 'abc123', 'missing.php');
    }
}
