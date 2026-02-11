<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamAccessToken>
 */
class TeamAccessTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by_user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'token' => hash('sha256', Str::random(64)),
            'last_used_at' => null,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $user->id,
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now()->subMinutes(rand(1, 60)),
        ]);
    }
}
