<?php

use App\Http\Controllers\CoverageController;
use App\Http\Controllers\CoverageDiffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.token')->group(function () {
    Route::post('/coverage/{report}/diff', [CoverageDiffController::class, 'store'])->name('api.coverage.diff.store');
    Route::post('/coverage', [CoverageController::class, 'store'])->name('api.coverage.store');
    Route::get('/coverage/status/{report}', [CoverageController::class, 'status'])->name('api.coverage.status');
});
