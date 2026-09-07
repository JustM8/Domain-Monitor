<?php

use App\Modules\Ftp\Http\Controllers\FtpAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('ftp', [FtpAccountController::class, 'index'])->middleware('role:admin,developer')->name('ftp.index');
    Route::get('ftp/add', [FtpAccountController::class, 'add'])->middleware('role:admin,developer')->name('ftp.add');
    Route::get('ftp/{ftpAccount}/edit', [FtpAccountController::class, 'edit'])->middleware('role:admin,developer')->name('ftp.edit');
    Route::get('ftp/{ftpAccount}/filezilla', [FtpAccountController::class, 'filezilla'])->middleware('role:admin,developer')->name('ftp.filezilla');
    Route::post('ftp', [FtpAccountController::class, 'store'])->middleware('role:admin,developer')->name('ftp.store');
    Route::put('ftp/{ftpAccount}', [FtpAccountController::class, 'update'])->middleware('role:admin,developer')->name('ftp.update');
    Route::delete('ftp/{ftpAccount}', [FtpAccountController::class, 'destroy'])->middleware('role:admin,developer')->name('ftp.destroy');
    Route::post('ftp/{ftpAccount}/restore', [FtpAccountController::class, 'restore'])->middleware('role:admin,developer')->name('ftp.restore');
    Route::post('ftp/{ftpAccount}/force-delete', [FtpAccountController::class, 'forceDelete'])->middleware('role:admin')->name('ftp.force-delete');
});
