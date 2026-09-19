<?php

use App\Modules\TelegramAccess\Http\Controllers\AccessLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])->prefix('portal')->name('portal.')->group(function () {
    Route::post('/profile/telegram/request', [AccessLinkController::class, 'request'])->middleware(['can:access.use', 'throttle:6,1'])->name('profile.telegram.request');
    Route::post('/users/{user}/telegram/approve', [AccessLinkController::class, 'approve'])->middleware('can:users.write')->name('users.telegram.approve');
    Route::post('/users/{user}/telegram/revoke', [AccessLinkController::class, 'revoke'])->middleware('can:users.write')->name('users.telegram.revoke');
});
