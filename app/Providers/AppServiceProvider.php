<?php

namespace App\Providers;

use App\Contracts\GitHubServiceInterface;
use App\Services\CachedGitHubService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GitHubServiceInterface::class, CachedGitHubService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceHttps();
        }
    }
}
