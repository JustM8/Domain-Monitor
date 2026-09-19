<?php

use App\Modules\Ftp\Http\Controllers\FtpAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('ftp', [FtpAccountController::class, 'index'])->middleware('can:ftp.read')->name('ftp.index');
    Route::get('ftp/add', [FtpAccountController::class, 'add'])->middleware('can:ftp.write')->name('ftp.add');
    Route::get('ftp/{ftpAccount}/edit', [FtpAccountController::class, 'edit'])->middleware('can:ftp.read')->name('ftp.edit');
    Route::get('ftp/{ftpAccount}/filezilla', [FtpAccountController::class, 'filezilla'])->middleware('can:ftp.read')->name('ftp.filezilla');
    Route::post('ftp', [FtpAccountController::class, 'store'])->middleware('can:ftp.write')->name('ftp.store');
    Route::put('ftp/{ftpAccount}', [FtpAccountController::class, 'update'])->middleware('can:ftp.write')->name('ftp.update');
    Route::delete('ftp/{ftpAccount}', [FtpAccountController::class, 'destroy'])->middleware('can:ftp.delete')->name('ftp.destroy');
    Route::post('ftp/{ftpAccount}/restore', [FtpAccountController::class, 'restore'])->middleware('can:ftp.delete')->name('ftp.restore');
    Route::post('ftp/{ftpAccount}/force-delete', [FtpAccountController::class, 'forceDelete'])->middleware('can:records.purge')->name('ftp.force-delete');
});
