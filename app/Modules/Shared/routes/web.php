<?php

use App\Modules\Shared\Http\Controllers\DashboardController;
use App\Modules\UserManagement\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/portal', [DashboardController::class, 'index'])->name('portal.dashboard');
    Route::get('/portal/profile', [ProfileController::class, 'edit'])->name('portal.profile.edit');
    Route::put('/portal/profile', [ProfileController::class, 'update'])->name('portal.profile.update');
    Route::post('/portal/profile/verification', [ProfileController::class, 'sendVerification'])->name('portal.profile.verification.send');
    Route::post('/portal/profile/telegram/request', [ProfileController::class, 'requestTelegramAccess'])->name('portal.profile.telegram.request');
});
