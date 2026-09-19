<?php

use App\Modules\UserManagement\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::post('users/{user}/approve', [UserController::class, 'approve'])->middleware('can:approve-user,user')->name('users.approve');
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.read')->name('users.index');
    Route::get('users/add', [UserController::class, 'add'])->middleware('can:users.write')->name('users.add');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('can:users.write')->name('users.edit');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.write')->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.write')->name('users.update');
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->middleware('can:users.write')->name('users.toggle-active');
    Route::post('users/{user}/verification', [UserController::class, 'sendVerification'])->middleware('can:users.write')->name('users.send-verification');

});
