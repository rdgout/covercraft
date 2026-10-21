<?php

namespace Tests\Feature\Actions;

use App\Actions\QueueChangedLineCoverageAction;
use App\Jobs\CalculateChangedLineCoverageJob;
use App\Models\CoverageReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueChangedLineCoverageActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_waits_for_inputs_and_repeat_readiness_can_recover_a_lost_dispatch(): void
    {
        Queue::fake();
        $report = CoverageReport::factory()->pending()->create(['diff_sha256' => str_repeat('a', 64), 'diff_status' => 'completed']);
        $ready = new QueueChangedLineCoverageAction;
        $ready->execute($report);
        $this->assertSame('awaiting_coverage', $report->fresh()->patch_coverage['status']);
        Queue::assertNothingPushed();
        $report->update(['status' => 'completed']);
        $ready->execute($report);
        Queue::assertPushed(CalculateChangedLineCoverageJob::class, 1);
        Queue::fake();
        $ready->execute($report);
        Queue::assertPushed(CalculateChangedLineCoverageJob::class, 1);
        $report->update(['patch_coverage' => ['status' => 'completed']]);
        Queue::fake();
        $ready->execute($report);
        Queue::assertNothingPushed();
    }

    public function test_clover_and_diff_failures_are_independent(): void
    {
        Queue::fake();
        $report = CoverageReport::factory()->create(['diff_sha256' => str_repeat('a', 64), 'diff_status' => 'failed', 'diff_error_code' => 'invalid_diff']);
        (new QueueChangedLineCoverageAction)->execute($report);
        $this->assertSame('completed', $report->fresh()->status);
        $this->assertSame('unavailable', $report->fresh()->patch_coverage['status']);
        Queue::assertNothingPushed();
    }
}
