<?php

use App\Modules\Monitoring\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'can:monitoring.read'])->prefix('portal/monitoring')->name('portal.monitoring.')->group(function () {
    Route::get('/', [MonitoringController::class, 'index'])->name('index');
    Route::post('/settings', [MonitoringController::class, 'settings'])->middleware('can:monitoring.write')->name('settings');
    Route::put('/sites/{site}/settings', [MonitoringController::class, 'updateSite'])->middleware('can:monitoring.write')->name('site-settings');
    Route::get('/sites/{site}', [MonitoringController::class, 'show'])->name('show');
    Route::post('/sites/{site}/check', [MonitoringController::class, 'check'])->middleware('throttle:10,1')->name('check');
});
