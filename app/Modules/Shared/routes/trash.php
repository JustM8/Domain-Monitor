<?php

use App\Modules\Shared\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('trash', [TrashController::class, 'index'])->middleware('role:admin,pm,developer')->name('trash.index');
});
