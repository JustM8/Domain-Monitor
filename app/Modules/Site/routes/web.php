<?php

use App\Modules\Site\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('sites', [SiteController::class, 'index'])->middleware('role:admin,pm,developer')->name('sites.index');
    Route::get('sites/add', [SiteController::class, 'add'])->middleware('role:admin,pm')->name('sites.add');
    Route::get('sites/{site}', [SiteController::class, 'show'])->middleware('role:admin,pm,developer')->name('sites.show');
    Route::post('sites', [SiteController::class, 'store'])->middleware('role:admin,pm')->name('sites.store');
    Route::put('sites/{site}', [SiteController::class, 'update'])->middleware('role:admin,pm')->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->middleware('role:admin')->name('sites.destroy');
    Route::post('sites/{site}/check', [SiteController::class, 'check'])->middleware('role:admin,pm,developer')->name('sites.check');
    Route::post('sites/{site}/sync', [SiteController::class, 'sync'])->middleware('role:admin,pm,developer')->name('sites.sync');
    Route::post('sites/{site}/restore', [SiteController::class, 'restore'])->middleware('role:admin')->name('sites.restore');
    Route::post('sites/{site}/force-delete', [SiteController::class, 'forceDelete'])->middleware('role:admin')->name('sites.force-delete');
    Route::post('sites/{site}/remote-control', [SiteController::class, 'toggleRemoteControl'])->middleware('role:admin,pm')->name('sites.remote-control');
    Route::post('sites/{site}/disable', [SiteController::class, 'disable'])->middleware('role:admin,pm')->name('sites.disable');
    Route::post('sites/{site}/enable', [SiteController::class, 'enable'])->middleware('role:admin,pm')->name('sites.enable');
});
