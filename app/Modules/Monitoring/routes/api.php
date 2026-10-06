<?php

use App\Modules\Monitoring\Http\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::post('/monitoring/heartbeat', HeartbeatController::class)->middleware(['throttle:120,1', \App\Modules\Monitoring\Http\Middleware\MonitoringReady::class])->name('monitoring.heartbeat');
