<?php

use App\Modules\TelegramSupport\Http\Controllers\TelegramSupportWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/support/webhook', TelegramSupportWebhookController::class)->name('telegram.support.webhook');
