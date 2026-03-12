<?php

namespace Tests\Feature\Services;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Services\PullRequestCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PullRequestCommentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PullRequestCommentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PullRequestCommentService;
    }

    public function test_build_comment_body_contains_marker(): void
    {
        $report = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($report, null, []);

        $this->assertStringContainsString('<!-- covercraft-report -->', $body);
    }

    public function test_build_comment_body_shows_head_branch_and_coverage(): void
    {
        $report = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($report, null, []);

        $this->assertStringContainsString('`feature/x`', $body);
        $this->assertStringContainsString('87.50%', $body);
    }

    public function test_build_comment_body_shows_improvement_emoji(): void
    {
        $baseReport = $this->makeReport('main', 85.20);
        $headReport = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($headReport, $baseReport, []);

        $this->assertStringContainsString('✅', $body);
        $this->assertStringContainsString('+2.30%', $body);
    }

    public function test_build_comment_body_shows_regression_emoji(): void
    {
        $baseReport = $this->makeReport('main', 90.00);
        $headReport = $this->makeReport('feature/x', 85.00);

        $body = $this->service->buildCommentBody($headReport, $baseReport, []);

        $this->assertStringContainsString('⚠️', $body);
        $this->assertStringContainsString('-5.00%', $body);
    }

    public function test_build_comment_body_shows_no_change_emoji(): void
    {
        $baseReport = $this->makeReport('main', 85.00);
        $headReport = $this->makeReport('feature/x', 85.00);

        $body = $this->service->buildCommentBody($headReport, $baseReport, []);

        $this->assertStringContainsString('➡️', $body);
    }

    public function test_build_comment_body_without_base_report_shows_dash(): void
    {
        $report = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($report, null, []);

        $this->assertStringContainsString('| Base | — | — |', $body);
    }

    public function test_build_comment_body_shows_changed_files_table(): void
    {
        $report = $this->makeReport('feature/x', 87.50);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'app/Foo.php',
            'coverage_percentage' => 90.00,
        ]);
        $report->load('files');

        $body = $this->service->buildCommentBody($report, null, ['app/Foo.php']);

        $this->assertStringContainsString('### Changed Files', $body);
        $this->assertStringContainsString('`app/Foo.php`', $body);
        $this->assertStringContainsString('90.00%', $body);
    }

    public function test_build_comment_body_omits_changed_files_table_when_no_changed_files(): void
    {
        $report = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($report, null, []);

        $this->assertStringNotContainsString('### Changed Files', $body);
    }

    public function test_build_comment_body_only_shows_files_in_changed_list(): void
    {
        $report = $this->makeReport('feature/x', 87.50);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'app/Foo.php',
            'coverage_percentage' => 90.00,
        ]);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'app/Bar.php',
            'coverage_percentage' => 70.00,
        ]);
        $report->load('files');

        $body = $this->service->buildCommentBody($report, null, ['app/Foo.php']);

        $this->assertStringContainsString('`app/Foo.php`', $body);
        $this->assertStringNotContainsString('`app/Bar.php`', $body);
    }

    public function test_build_comment_body_contains_covercraft_link(): void
    {
        $report = $this->makeReport('feature/x', 87.50);

        $body = $this->service->buildCommentBody($report, null, []);

        $this->assertStringContainsString('CoverCraft', $body);
        $this->assertStringContainsString('dashboard', $body);
    }

    private function makeReport(string $branch, float $coverage): CoverageReport
    {
        $repository = Repository::factory()->create(['default_branch' => 'main']);

        return CoverageReport::factory()->create([
            'repository_id' => $repository->id,
            'branch' => $branch,
            'coverage_percentage' => $coverage,
            'status' => 'completed',
        ]);
    }
}
