<?php

namespace Database\Factories;

use App\Models\Repository;
use App\Models\RepositoryFileCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryFileCache>
 */
class RepositoryFileCacheFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileCount = fake()->numberBetween(5, 20);
        $files = [];

        for ($i = 0; $i < $fileCount; $i++) {
            $files[] = 'src/'.fake()->slug(2).'.php';
        }

        return [
            'repository_id' => Repository::factory(),
            'branch' => 'main',
            'commit_sha' => fake()->sha1(),
            'files' => $files,
            'cached_at' => now(),
        ];
    }
}
