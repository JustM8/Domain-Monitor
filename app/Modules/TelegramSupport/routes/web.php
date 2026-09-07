<?php

use App\Modules\TelegramSupport\Http\Controllers\SupportAnalyticsController;
use App\Modules\TelegramSupport\Http\Controllers\SupportTicketController;
use App\Modules\TelegramSupport\Http\Controllers\SupportClientController;
use App\Modules\TelegramSupport\Http\Controllers\SupportSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user', 'role:admin'])
    ->prefix('portal/support')
    ->name('portal.support.')
    ->group(function () {
        Route::get('/clients', [SupportClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/add', [SupportClientController::class, 'add'])->name('clients.add');
        Route::post('/clients', [SupportClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}/edit', [SupportClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [SupportClientController::class, 'update'])->name('clients.update');
        Route::get('/settings', [SupportSettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [SupportSettingsController::class, 'update'])->name('settings.update');
        Route::get('/analytics', [SupportAnalyticsController::class, 'index'])->name('analytics');
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
        Route::post('/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('status');
        Route::post('/tickets/{ticket}/pm', [SupportTicketController::class, 'togglePm'])->name('pm');
    });
