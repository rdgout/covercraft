<?php

namespace Database\Factories;

use App\Models\CoverageReport;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageReport>
 */
class CoverageReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'branch' => fake()->randomElement(['main', 'develop', 'feature/'.fake()->slug(2)]),
            'commit_sha' => fake()->sha1(),
            'coverage_percentage' => fake()->randomFloat(2, 0, 100),
            'status' => 'completed',
            'error_message' => null,
            'clover_file_path' => 'coverage/'.fake()->uuid().'.xml',
            'archived' => false,
            'archived_at' => null,
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'coverage_percentage' => null,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'coverage_percentage' => null,
            'error_message' => fake()->sentence(),
            'completed_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived' => true,
            'archived_at' => now(),
        ]);
    }

    public function onBranch(string $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch' => $branch,
        ]);
    }
}
