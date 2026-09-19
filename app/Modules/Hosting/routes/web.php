<?php

use App\Modules\Hosting\Http\Controllers\HostingAccountController;
use App\Modules\Hosting\Http\Controllers\HostingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('hosting', [HostingController::class, 'index'])->middleware('can:hosting.read')->name('hosting.index');
    Route::get('hosting/add', [HostingController::class, 'add'])->middleware('can:hosting.write')->name('hosting.add');
    Route::get('hosting/{hosting}/edit', [HostingController::class, 'edit'])->middleware('can:hosting.read')->name('hosting.edit');
    Route::post('hosting', [HostingController::class, 'store'])->middleware('can:hosting.write')->name('hosting.store');
    Route::put('hosting/{hosting}', [HostingController::class, 'update'])->middleware('can:hosting.write')->name('hosting.update');
    Route::delete('hosting/{hosting}', [HostingController::class, 'destroy'])->middleware('can:hosting.delete')->name('hosting.destroy');
    Route::post('hosting/{hosting}/restore', [HostingController::class, 'restore'])->middleware('can:hosting.delete')->name('hosting.restore');
    Route::post('hosting/{hosting}/force-delete', [HostingController::class, 'forceDelete'])->middleware('can:records.purge')->name('hosting.force-delete');

    Route::get('hosting-accounts', [HostingAccountController::class, 'index'])->middleware('can:hosting-accounts.read')->name('hosting-accounts.index');
    Route::get('hosting-accounts/add', [HostingAccountController::class, 'add'])->middleware('can:hosting-accounts.write')->name('hosting-accounts.add');
    Route::get('hosting-accounts/{hostingAccount}/edit', [HostingAccountController::class, 'edit'])->middleware('can:hosting-accounts.read')->name('hosting-accounts.edit');
    Route::post('hosting-accounts', [HostingAccountController::class, 'store'])->middleware('can:hosting-accounts.write')->name('hosting-accounts.store');
    Route::put('hosting-accounts/{hostingAccount}', [HostingAccountController::class, 'update'])->middleware('can:hosting-accounts.write')->name('hosting-accounts.update');
    Route::delete('hosting-accounts/{hostingAccount}', [HostingAccountController::class, 'destroy'])->middleware('can:hosting-accounts.delete')->name('hosting-accounts.destroy');
    Route::post('hosting-accounts/{hostingAccount}/restore', [HostingAccountController::class, 'restore'])->middleware('can:hosting-accounts.delete')->name('hosting-accounts.restore');
    Route::post('hosting-accounts/{hostingAccount}/force-delete', [HostingAccountController::class, 'forceDelete'])->middleware('can:records.purge')->name('hosting-accounts.force-delete');
});
