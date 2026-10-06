<?php

use App\Modules\Monitoring\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'can:monitoring.read', \App\Modules\Monitoring\Http\Middleware\MonitoringReady::class])->prefix('portal/monitoring')->name('portal.monitoring.')->group(function () {
    Route::get('/', [MonitoringController::class, 'index'])->name('index');
    Route::get('/settings', [MonitoringController::class, 'settings'])->name('settings');
    Route::post('/settings', [MonitoringController::class, 'saveSettings'])->middleware('can:monitoring.write')->name('settings.save');
    Route::post('/monitors', [MonitoringController::class, 'store'])->middleware('can:monitoring.write')->name('store');
    Route::get('/monitors/{monitor}', [MonitoringController::class, 'show'])->name('show');
    Route::put('/monitors/{monitor}', [MonitoringController::class, 'update'])->middleware('can:monitoring.write')->name('update');
    Route::post('/monitors/{monitor}/check', [MonitoringController::class, 'check'])->middleware('throttle:10,1')->name('check');
    Route::post('/monitors/{monitor}/credential', [MonitoringController::class, 'credential'])->middleware('can:monitoring.write')->name('credential');
    Route::put('/monitors/{monitor}/timezone', [MonitoringController::class, 'preference'])->middleware('can:monitoring.write')->name('timezone');
    Route::get('/sites/{site}', [MonitoringController::class, 'site'])->name('site');
});
