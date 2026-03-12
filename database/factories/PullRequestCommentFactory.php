<?php

namespace Database\Factories;

use App\Models\PullRequestComment;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PullRequestComment>
 */
class PullRequestCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'pr_number' => fake()->numberBetween(1, 999),
            'github_comment_id' => null,
        ];
    }

    public function withGithubComment(): static
    {
        return $this->state(fn (array $attributes) => [
            'github_comment_id' => fake()->numberBetween(1000000, 9999999),
        ]);
    }
}
