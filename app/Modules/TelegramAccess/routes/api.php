<?php

use App\Modules\TelegramAccess\Http\Controllers\TelegramAccessWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramAccessWebhookController::class)->name('telegram.webhook');
