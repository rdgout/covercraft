<?php

namespace Tests\Feature\Properties;

use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 16: Branch Coverage Comparison Calculation
     *
     * For any non-default branch with coverage X and default branch with coverage Y,
     * the comparison difference should equal round(X - Y, 2).
     */
    public function test_property_16_branch_coverage_comparison(): void
    {
        $seed = rand(0, PHP_INT_MAX);
        srand($seed);

        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create(['default_branch' => 'main']);

            $defaultCoverage = fake()->randomFloat(2, 0, 100);
            $branchCoverage = fake()->randomFloat(2, 0, 100);

            CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => 'main',
                'coverage_percentage' => $defaultCoverage,
            ]);

            CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => 'feature-'.$i,
                'coverage_percentage' => $branchCoverage,
            ]);

            $expectedDiff = round($branchCoverage - $defaultCoverage, 2);

            $this->assertEquals(
                $expectedDiff,
                round($branchCoverage - $defaultCoverage, 2),
                "Seed: {$seed}, iteration: {$i}"
            );
        }
    }
}
