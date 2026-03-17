<?php

namespace App\Jobs;

use App\Services\CachedGitHubService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshGitHubRepositoriesCacheJob implements ShouldQueue
{
    use Queueable;

    public function handle(CachedGitHubService $service): void
    {
        $service->refreshRepositoriesCache();
    }
}
