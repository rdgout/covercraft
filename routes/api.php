<?php

use App\Http\Controllers\CoverageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.token')->group(function () {
    Route::post('/coverage', [CoverageController::class, 'store'])->name('api.coverage.store');
    Route::get('/coverage/status/{report}', [CoverageController::class, 'status'])->name('api.coverage.status');
});
