<?php

namespace App\Modules\TelegramAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TelegramAccess\Services\AccessUpdateHandler;
use App\Modules\TelegramAccess\Services\TelegramBotService;
use Illuminate\Http\Request;

final class TelegramAccessWebhookController extends Controller
{
    public function __invoke(Request $request, AccessUpdateHandler $handler, TelegramBotService $bot)
    {
        $handler->handle($request->all(), $bot);

        return response()->json(['ok' => true]);
    }
}
