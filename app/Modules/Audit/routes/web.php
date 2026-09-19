<?php

use App\Modules\Audit\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('activity', [ActivityLogController::class, 'index'])->middleware('can:activity.read')->name('activity.index');
});
