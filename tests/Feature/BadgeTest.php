<?php

namespace Tests\Feature;

use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('coverageTiers')]
    public function test_badge_uses_the_design_palette_and_percentage_format(float $coverage, string $color): void
    {
        $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'design']);
        CoverageReport::factory()->for($repository)->create(['branch' => 'main', 'coverage_percentage' => $coverage]);

        $response = $this->get('/badge/acme/design/main')->assertOk();
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $paths = $document->getElementsByTagName('path');
        $this->assertSame('#0d1c3c', $paths->item(0)->getAttribute('fill'));
        $this->assertSame($color, $paths->item(1)->getAttribute('fill'));
        $this->assertSame(number_format($coverage, 1).'%', $document->getElementsByTagName('text')->item(1)->textContent);
    }

    /**
     * @return list<array{float, string}>
     */
    public static function coverageTiers(): array
    {
        return [
            [0.0, '#ff7a59'],
            [49.9, '#ff7a59'],
            [50.0, '#ffc94a'],
            [79.9, '#ffc94a'],
            [80.0, '#72cad7'],
            [100.0, '#72cad7'],
        ];
    }

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
        $this->assertStringContainsString('85.5%', $response->getContent());
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
        $this->assertStringNotContainsString('85.5%', $response->getContent());
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
        $this->assertStringContainsString('90.3%', $response->getContent());
        $this->assertStringNotContainsString('75.0%', $response->getContent());
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
        $this->assertStringContainsString('85.5%', $responseMain->getContent());

        $responseDevelop->assertOk();
        $this->assertStringContainsString('92.0%', $responseDevelop->getContent());
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
        $this->assertStringContainsString('88.8%', $response->getContent());
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
