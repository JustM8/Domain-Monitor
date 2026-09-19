<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;

final class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next, string $bot)
    {
        abort_unless(in_array($bot, ['telegram_access', 'telegram_support'], true), 403);
        $expected = (string) config($bot.'.webhook_secret');
        $actual = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_if($expected === '' || $actual === '' || ! hash_equals($expected, $actual), 403);

        return $next($request);
    }
}
