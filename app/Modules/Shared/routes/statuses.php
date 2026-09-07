<?php

use App\Modules\Shared\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'role:admin'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('statuses', [StatusController::class, 'index'])->name('statuses.index');
    Route::get('statuses/add', [StatusController::class, 'add'])->name('statuses.add');
    Route::get('statuses/{status}/edit', [StatusController::class, 'edit'])->name('statuses.edit');
    Route::post('statuses', [StatusController::class, 'store'])->name('statuses.store');
    Route::post('statuses/sync-defaults', [StatusController::class, 'syncDefaults'])->name('statuses.sync-defaults');
    Route::put('statuses/{status}', [StatusController::class, 'update'])->name('statuses.update');
    Route::post('statuses/{status}/archive', [StatusController::class, 'archive'])->name('statuses.toggle-archive');
    Route::post('statuses/{status}/restore', [StatusController::class, 'restore'])->name('statuses.restore');
});
