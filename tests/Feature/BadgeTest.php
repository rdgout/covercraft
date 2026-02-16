<?php

namespace Tests\Feature;

use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_displays_unknown_when_repository_does_not_exist(): void
    {
        $response = $this->get('/badge/nonexistent/repo/main');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('coverage', $response->getContent());
        $this->assertStringContainsString('?', $response->getContent());
    }

    public function test_badge_displays_question_mark_when_no_coverage_reports_exist(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('coverage', $response->getContent());
        $this->assertStringContainsString('?', $response->getContent());
    }

    public function test_badge_displays_coverage_percentage_when_report_exists(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '85.50',
                'status' => 'completed',
                'archived' => false,
            ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('coverage', $response->getContent());
        $this->assertStringContainsString('85.50', $response->getContent());
    }

    public function test_badge_ignores_archived_reports(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->archived()
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '85.50',
            ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $this->assertStringContainsString('?', $response->getContent());
        $this->assertStringNotContainsString('85.50', $response->getContent());
    }

    public function test_badge_ignores_non_completed_reports(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->pending()
            ->create([
                'branch' => 'main',
            ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $this->assertStringContainsString('?', $response->getContent());
    }

    public function test_badge_returns_latest_report_when_multiple_exist(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '75.00',
                'status' => 'completed',
                'created_at' => now()->subHours(2),
            ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '90.25',
                'status' => 'completed',
                'created_at' => now()->subHour(),
            ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $this->assertStringContainsString('90.25', $response->getContent());
        $this->assertStringNotContainsString('75.00', $response->getContent());
    }

    public function test_badge_handles_different_branches(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '85.50',
            ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'develop',
                'coverage_percentage' => '92.00',
            ]);

        $responseMain = $this->get('/badge/testowner/testrepo/main');
        $responseDevelop = $this->get('/badge/testowner/testrepo/develop');

        $responseMain->assertOk();
        $this->assertStringContainsString('85.50', $responseMain->getContent());

        $responseDevelop->assertOk();
        $this->assertStringContainsString('92.00', $responseDevelop->getContent());
    }

    public function test_badge_handles_branches_with_slashes(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'feature/awesome-feature',
                'coverage_percentage' => '88.75',
            ]);

        $response = $this->get('/badge/testowner/testrepo/feature/awesome-feature');

        $response->assertOk();
        $this->assertStringContainsString('88.75', $response->getContent());
    }

    public function test_badge_returns_cache_headers(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        CoverageReport::factory()
            ->for($repository)
            ->create([
                'branch' => 'main',
                'coverage_percentage' => '85.50',
            ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    public function test_badge_does_not_set_session_cookies(): void
    {
        $repository = Repository::factory()->create([
            'owner' => 'testowner',
            'name' => 'testrepo',
        ]);

        $response = $this->get('/badge/testowner/testrepo/main');

        $response->assertOk();
        $cookies = $response->headers->getCookies();
        $sessionCookies = array_filter($cookies, fn ($cookie) => str_contains($cookie->getName(), 'session'));
        $this->assertEmpty($sessionCookies, 'Badge endpoint should not set session cookies');
    }
}
