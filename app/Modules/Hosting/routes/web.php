<?php

use App\Modules\Hosting\Http\Controllers\HostingAccountController;
use App\Modules\Hosting\Http\Controllers\HostingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('hosting', [HostingController::class, 'index'])->middleware('role:admin,developer')->name('hosting.index');
    Route::get('hosting/add', [HostingController::class, 'add'])->middleware('role:admin,developer')->name('hosting.add');
    Route::get('hosting/{hosting}/edit', [HostingController::class, 'edit'])->middleware('role:admin,developer')->name('hosting.edit');
    Route::post('hosting', [HostingController::class, 'store'])->middleware('role:admin,developer')->name('hosting.store');
    Route::put('hosting/{hosting}', [HostingController::class, 'update'])->middleware('role:admin,developer')->name('hosting.update');
    Route::delete('hosting/{hosting}', [HostingController::class, 'destroy'])->middleware('role:admin')->name('hosting.destroy');
    Route::post('hosting/{hosting}/restore', [HostingController::class, 'restore'])->middleware('role:admin')->name('hosting.restore');
    Route::post('hosting/{hosting}/force-delete', [HostingController::class, 'forceDelete'])->middleware('role:admin')->name('hosting.force-delete');

    Route::get('hosting-accounts', [HostingAccountController::class, 'index'])->middleware('role:admin,developer')->name('hosting-accounts.index');
    Route::get('hosting-accounts/add', [HostingAccountController::class, 'add'])->middleware('role:admin,developer')->name('hosting-accounts.add');
    Route::get('hosting-accounts/{hostingAccount}/edit', [HostingAccountController::class, 'edit'])->middleware('role:admin,developer')->name('hosting-accounts.edit');
    Route::post('hosting-accounts', [HostingAccountController::class, 'store'])->middleware('role:admin,developer')->name('hosting-accounts.store');
    Route::put('hosting-accounts/{hostingAccount}', [HostingAccountController::class, 'update'])->middleware('role:admin,developer')->name('hosting-accounts.update');
    Route::delete('hosting-accounts/{hostingAccount}', [HostingAccountController::class, 'destroy'])->middleware('role:admin,developer')->name('hosting-accounts.destroy');
    Route::post('hosting-accounts/{hostingAccount}/restore', [HostingAccountController::class, 'restore'])->middleware('role:admin')->name('hosting-accounts.restore');
    Route::post('hosting-accounts/{hostingAccount}/force-delete', [HostingAccountController::class, 'forceDelete'])->middleware('role:admin')->name('hosting-accounts.force-delete');
});
