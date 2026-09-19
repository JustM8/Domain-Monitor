<?php

use App\Modules\Shared\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->middleware('can:companies.read')->name('companies.index');
    Route::get('companies/add', [CompanyController::class, 'add'])->middleware('can:companies.write')->name('companies.add');
    Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->middleware('can:companies.read')->name('companies.edit');
    Route::post('companies', [CompanyController::class, 'store'])->middleware('can:companies.write')->name('companies.store');
    Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('can:companies.write')->name('companies.update');
    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('can:companies.delete')->name('companies.destroy');
    Route::post('companies/{company}/restore', [CompanyController::class, 'restore'])->middleware('can:companies.delete')->name('companies.restore');
    Route::post('companies/{company}/force-delete', [CompanyController::class, 'forceDelete'])->middleware('can:records.purge')->name('companies.force-delete');
});
