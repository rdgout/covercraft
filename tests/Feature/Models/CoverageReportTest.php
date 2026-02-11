<?php

namespace Tests\Feature\Models;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_via_factory(): void
    {
        $report = CoverageReport::factory()->create();

        $this->assertDatabaseHas('coverage_reports', ['id' => $report->id]);
    }

    public function test_pending_state(): void
    {
        $report = CoverageReport::factory()->pending()->create();

        $this->assertEquals('pending', $report->status);
        $this->assertNull($report->coverage_percentage);
        $this->assertNull($report->completed_at);
    }

    public function test_failed_state(): void
    {
        $report = CoverageReport::factory()->failed()->create();

        $this->assertEquals('failed', $report->status);
        $this->assertNotNull($report->error_message);
    }

    public function test_archived_state(): void
    {
        $report = CoverageReport::factory()->archived()->create();

        $this->assertTrue($report->archived);
        $this->assertNotNull($report->archived_at);
    }

    public function test_on_branch_state(): void
    {
        $report = CoverageReport::factory()->onBranch('feature/login')->create();

        $this->assertEquals('feature/login', $report->branch);
    }

    public function test_belongs_to_repository(): void
    {
        $repository = Repository::factory()->create();
        $report = CoverageReport::factory()->create(['repository_id' => $repository->id]);

        $this->assertEquals($repository->id, $report->repository->id);
    }

    public function test_has_many_files(): void
    {
        $report = CoverageReport::factory()->create();
        CoverageFile::factory()->count(5)->create(['coverage_report_id' => $report->id]);

        $this->assertCount(5, $report->files);
    }

    public function test_scope_current_excludes_archived(): void
    {
        $repository = Repository::factory()->create();

        CoverageReport::factory()->archived()->create(['repository_id' => $repository->id]);
        CoverageReport::factory()->create(['repository_id' => $repository->id, 'archived' => false]);
        CoverageReport::factory()->create(['repository_id' => $repository->id, 'archived' => false]);

        $current = CoverageReport::current()->where('repository_id', $repository->id)->get();

        $this->assertCount(2, $current);
    }

    public function test_cascade_deletes_files(): void
    {
        $report = CoverageReport::factory()->create();
        CoverageFile::factory()->count(3)->create(['coverage_report_id' => $report->id]);

        $report->delete();

        $this->assertDatabaseCount('coverage_files', 0);
    }

    public function test_casts_are_correct(): void
    {
        $report = CoverageReport::factory()->create([
            'coverage_percentage' => 85.50,
            'archived' => true,
            'archived_at' => now(),
            'completed_at' => now(),
        ]);

        $report->refresh();

        $this->assertIsBool($report->archived);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $report->archived_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $report->completed_at);
    }
}
