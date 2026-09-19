<?php

use App\Modules\TelegramAccess\Http\Controllers\TelegramAccessWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramAccessWebhookController::class)->middleware('telegram.webhook:telegram_access')->name('telegram.webhook');
