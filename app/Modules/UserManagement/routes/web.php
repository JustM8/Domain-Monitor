<?php

use App\Modules\UserManagement\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'role:admin'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/add', [UserController::class, 'add'])->name('users.add');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('users/{user}/verification', [UserController::class, 'sendVerification'])->name('users.send-verification');
    Route::post('users/{user}/telegram/approve', [UserController::class, 'approveTelegram'])->name('users.telegram.approve');
    Route::post('users/{user}/telegram/revoke', [UserController::class, 'revokeTelegram'])->name('users.telegram.revoke');
});
