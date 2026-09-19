<?php

use App\Modules\Site\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('sites', [SiteController::class, 'index'])->middleware('can:sites.read')->name('sites.index');
    Route::get('sites/add', [SiteController::class, 'add'])->middleware('can:sites.write')->name('sites.add');
    Route::get('sites/{site}', [SiteController::class, 'show'])->middleware('can:sites.read')->name('sites.show');
    Route::post('sites', [SiteController::class, 'store'])->middleware('can:sites.write')->name('sites.store');
    Route::put('sites/{site}', [SiteController::class, 'update'])->middleware('can:sites.write')->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->middleware('can:sites.delete')->name('sites.destroy');
    Route::post('sites/{site}/check', [SiteController::class, 'check'])->middleware(['can:sites.read', 'throttle:10,1'])->name('sites.check');
    Route::post('sites/{site}/sync', [SiteController::class, 'sync'])->middleware('can:sites.control')->name('sites.sync');
    Route::post('sites/{site}/restore', [SiteController::class, 'restore'])->middleware('can:sites.delete')->name('sites.restore');
    Route::post('sites/{site}/force-delete', [SiteController::class, 'forceDelete'])->middleware('can:records.purge')->name('sites.force-delete');
    Route::post('sites/{site}/remote-control', [SiteController::class, 'toggleRemoteControl'])->middleware('can:sites.control')->name('sites.remote-control');
    Route::post('sites/{site}/disable', [SiteController::class, 'disable'])->middleware('can:sites.control')->name('sites.disable');
    Route::post('sites/{site}/enable', [SiteController::class, 'enable'])->middleware('can:sites.control')->name('sites.enable');
});
