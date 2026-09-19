<?php

use App\Modules\Shared\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('statuses', [StatusController::class, 'index'])->middleware('can:statuses.read')->name('statuses.index');
    Route::get('statuses/add', [StatusController::class, 'add'])->middleware('can:statuses.write')->name('statuses.add');
    Route::get('statuses/{status}/edit', [StatusController::class, 'edit'])->middleware('can:statuses.read')->name('statuses.edit');
    Route::post('statuses', [StatusController::class, 'store'])->middleware('can:statuses.write')->name('statuses.store');
    Route::post('statuses/sync-defaults', [StatusController::class, 'syncDefaults'])->middleware('can:statuses.write')->name('statuses.sync-defaults');
    Route::put('statuses/{status}', [StatusController::class, 'update'])->middleware('can:statuses.write')->name('statuses.update');
    Route::post('statuses/{status}/archive', [StatusController::class, 'archive'])->middleware('can:statuses.write')->name('statuses.toggle-archive');
    Route::post('statuses/{status}/restore', [StatusController::class, 'restore'])->middleware('can:statuses.write')->name('statuses.restore');
});
