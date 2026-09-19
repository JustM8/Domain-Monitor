<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TelegramSupport\Services\SupportUpdateHandler;
use App\Modules\TelegramSupport\Services\TelegramSupportBotService;
use Illuminate\Http\Request;

final class TelegramSupportWebhookController extends Controller
{
    public function __invoke(Request $request, SupportUpdateHandler $handler, TelegramSupportBotService $bot)
    {
        $id = $request->input('update_id');
        abort_unless(is_int($id) && $id >= 0, 422);
        app(\App\Modules\TelegramSupport\Services\SupportWebhookInbox::class)->handle($request->all(), $handler, $bot);

        return response()->json(['ok' => true]);
    }
}
