<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::resource('repositories', RepositoryController::class)->except(['show']);
Route::post('/repositories/branches', [RepositoryController::class, 'fetchBranches'])->name('repositories.branches');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/{repository}', [DashboardController::class, 'repository'])->name('dashboard.repository');
Route::get('/dashboard/{repository}/{branch}/file', [DashboardController::class, 'file'])->name('dashboard.file')->where('branch', '.*');
Route::get('/dashboard/{repository}/{branch}', [DashboardController::class, 'branch'])->name('dashboard.branch')->where('branch', '.*');

Route::post('/webhooks/github', [WebhookController::class, 'github'])->name('webhooks.github');
