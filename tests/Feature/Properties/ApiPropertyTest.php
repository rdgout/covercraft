<?php

namespace Tests\Feature\Properties;

use App\Jobs\ProcessCoverageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    /**
     * Property 1: API Request Validation
     *
     * For any API request to submit coverage, if the request is missing required
     * fields or contains empty values, then the API should reject with 422.
     */
    public function test_property_1_api_request_validation(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        $requiredFields = ['repository', 'branch', 'commit_sha', 'clover_file'];

        for ($i = 0; $i < 100; $i++) {
            $fieldToOmit = $requiredFields[array_rand($requiredFields)];

            $payload = [
                'repository' => 'owner-'.rand(1, 100).'/repo-'.rand(1, 100),
                'branch' => 'branch-'.rand(1, 100),
                'commit_sha' => str_repeat(dechex(rand(0, 15)), 40),
                'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
            ];

            unset($payload[$fieldToOmit]);

            $response = $this->postJson('/api/coverage', $payload);

            $response->assertStatus(422, "Seed: {$seed}, iteration: {$i}, omitted: {$fieldToOmit}");
        }
    }

    /**
     * Property 2: Unique File Storage
     *
     * For any set of coverage submissions, each clover.xml file should be
     * stored with a unique filename.
     */
    public function test_property_2_unique_file_storage(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        $storedPaths = [];

        for ($i = 0; $i < 100; $i++) {
            $this->postJson('/api/coverage', [
                'repository' => 'acme/app',
                'branch' => 'main',
                'commit_sha' => fake()->sha1(),
                'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
            ]);
        }

        $files = Storage::disk('local')->files('coverage');

        $this->assertCount(100, $files, "Seed: {$seed}");
        $this->assertCount(100, array_unique($files), "Seed: {$seed} - duplicate filenames detected");
    }

    /**
     * Property 3: Job Queuing
     *
     * For any valid coverage submission, a processing job should exist in the queue.
     */
    public function test_property_3_job_queuing(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            Queue::fake();

            $response = $this->postJson('/api/coverage', [
                'repository' => 'owner-'.rand(1, 100).'/repo-'.rand(1, 100),
                'branch' => 'branch-'.rand(1, 100),
                'commit_sha' => fake()->sha1(),
                'clover_file' => UploadedFile::fake()->create('clover.xml', 100),
            ]);

            $response->assertStatus(202, "Seed: {$seed}, iteration: {$i}");

            Queue::assertPushed(ProcessCoverageJob::class);
        }
    }
}
