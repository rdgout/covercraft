<?php

namespace Tests\Feature\Properties;

use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 14: Repository File Caching on Submission (cache hit)
     *
     * If a cached file list exists for the same commit, reuse it without fetching.
     */
    public function test_property_14_cache_hit_reuses_cached_data(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        Http::fake();
        $service = new GitHubService;

        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create();
            $branch = 'branch-'.$i;
            $commitSha = fake()->sha1();

            $cachedFiles = ['src/cached_'.$i.'.php', 'src/other_'.$i.'.php'];
            RepositoryFileCache::factory()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'commit_sha' => $commitSha,
                'files' => $cachedFiles,
            ]);

            $files = $service->getOrFetchRepositoryFiles($repository, $branch, $commitSha);

            $this->assertEquals($cachedFiles, $files, "Seed: {$seed}, iteration: {$i}");
        }

        Http::assertNothingSent();
    }

    /**
     * Property 14: Repository File Caching on Submission (cache miss)
     *
     * If no cache or different commit SHA, fetch from GitHub and store in cache.
     */
    public function test_property_14_cache_miss_fetches_and_caches(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        Http::fake([
            '*' => Http::response([
                'tree' => [
                    ['path' => 'src/Fetched.php', 'type' => 'blob'],
                ],
            ]),
        ]);

        $service = new GitHubService;

        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create();
            $branch = 'branch-'.$i;
            $commitSha = fake()->sha1();

            $files = $service->getOrFetchRepositoryFiles($repository, $branch, $commitSha);

            $this->assertContains('src/Fetched.php', $files, "Seed: {$seed}, iteration: {$i}");

            $this->assertDatabaseHas('repository_file_cache', [
                'repository_id' => $repository->id,
                'branch' => $branch,
                'commit_sha' => $commitSha,
            ]);
        }
    }

    /**
     * Property 15: Webhook Signature Verification
     *
     * Signature verification should return true iff the HMAC matches.
     */
    public function test_property_15_webhook_signature_verification(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        $service = new GitHubService;

        for ($i = 0; $i < 100; $i++) {
            $payload = json_encode(['iteration' => $i, 'data' => fake()->sentence()]);
            $secret = fake()->password(20, 40);

            $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

            $this->assertTrue(
                $service->verifyWebhookSignature($payload, $validSignature, $secret),
                "Seed: {$seed}, iteration: {$i} - valid signature should pass"
            );

            $wrongSignature = 'sha256='.hash_hmac('sha256', $payload.'tampered', $secret);

            $this->assertFalse(
                $service->verifyWebhookSignature($payload, $wrongSignature, $secret),
                "Seed: {$seed}, iteration: {$i} - invalid signature should fail"
            );
        }
    }
}
