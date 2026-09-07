<?php

use App\Modules\Shared\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->middleware('role:admin,pm')->name('companies.index');
    Route::get('companies/add', [CompanyController::class, 'add'])->middleware('role:admin,pm')->name('companies.add');
    Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->middleware('role:admin,pm')->name('companies.edit');
    Route::post('companies', [CompanyController::class, 'store'])->middleware('role:admin,pm')->name('companies.store');
    Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('role:admin,pm')->name('companies.update');
    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('role:admin')->name('companies.destroy');
    Route::post('companies/{company}/restore', [CompanyController::class, 'restore'])->middleware('role:admin')->name('companies.restore');
    Route::post('companies/{company}/force-delete', [CompanyController::class, 'forceDelete'])->middleware('role:admin')->name('companies.force-delete');
});
