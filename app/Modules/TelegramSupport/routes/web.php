<?php

use App\Modules\TelegramSupport\Http\Controllers\SupportAnalyticsController;
use App\Modules\TelegramSupport\Http\Controllers\SupportClientController;
use App\Modules\TelegramSupport\Http\Controllers\SupportSettingsController;
use App\Modules\TelegramSupport\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.user'])
    ->prefix('portal/support')
    ->name('portal.support.')
    ->group(function () {
        Route::get('/clients', [SupportClientController::class, 'index'])->middleware('can:support.read')->name('clients.index');
        Route::get('/clients/add', [SupportClientController::class, 'add'])->middleware('can:support.write')->name('clients.add');
        Route::post('/clients', [SupportClientController::class, 'store'])->middleware('can:support.write')->name('clients.store');
        Route::get('/clients/{client}/edit', [SupportClientController::class, 'edit'])->middleware('can:support.read')->name('clients.edit');
        Route::put('/clients/{client}', [SupportClientController::class, 'update'])->middleware('can:support.write')->name('clients.update');
        Route::get('/settings', [SupportSettingsController::class, 'index'])->middleware('can:support.read')->name('settings');
        Route::post('/settings', [SupportSettingsController::class, 'update'])->middleware('can:support.write')->name('settings.update');
        Route::get('/analytics', [SupportAnalyticsController::class, 'index'])->middleware('can:support.read')->name('analytics');
        Route::get('/', [SupportTicketController::class, 'index'])->middleware('can:support.read')->name('index');
        Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->middleware('can:support.read')->name('show');
        Route::post('/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware('can:support.reply')->name('reply');
        Route::post('/tickets/{ticket}/messages/{message}/retry', [SupportTicketController::class, 'retryReply'])->middleware(['can:support.reply', 'throttle:10,1'])->name('reply.retry');
        Route::post('/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->middleware('can:support.write')->name('status');
        Route::post('/tickets/{ticket}/pm', [SupportTicketController::class, 'togglePm'])->middleware('can:support.write')->name('pm');
    });
