<?php

use App\Http\Controllers\BadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\TeamAccessTokenController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/badge/{owner}/{name}/{branch}', [BadgeController::class, 'show'])
    ->name('badge.show')
    ->where('branch', '.*')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->middleware('cache.headers:public;max_age=300;etag');

Route::middleware('auth')->group(function () {
    Route::resource('repositories', RepositoryController::class)->except(['show']);
    Route::post('/repositories/branches', [RepositoryController::class, 'fetchBranches'])->name('repositories.branches');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/{repository}', [DashboardController::class, 'repository'])->name('dashboard.repository');
    Route::get('/dashboard/{repository}/{branch}/file', [DashboardController::class, 'file'])->name('dashboard.file')->where('branch', '.*');
    Route::get('/dashboard/{repository}/{branch}', [DashboardController::class, 'branch'])->name('dashboard.branch')->where('branch', '.*');

    Route::get('/tokens', [TeamAccessTokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [TeamAccessTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/tokens/{token}', [TeamAccessTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/webhooks/github', [WebhookController::class, 'github'])->name('webhooks.github');

require __DIR__.'/auth.php';
