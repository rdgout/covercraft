<?php

namespace Database\Factories;

use App\Models\Repository;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = fake()->userName();
        $name = fake()->slug(2);

        return [
            'team_id' => Team::factory(),
            'owner' => $owner,
            'name' => $name,
            'github_url' => "https://github.com/{$owner}/{$name}",
            'default_branch' => 'main',
            'webhook_secret' => null,
        ];
    }

    public function withWebhookSecret(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_secret' => fake()->sha256(),
        ]);
    }

    public function withDefaultBranch(string $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'default_branch' => $branch,
        ]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }
}
