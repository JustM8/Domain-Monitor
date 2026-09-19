<?php

namespace App\Modules\TelegramSupport\Services;

use App\Modules\TelegramSupport\Models\SupportMessage;
use App\Modules\TelegramSupport\Models\SupportSession;
use App\Modules\TelegramSupport\Models\SupportTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupportReplyDelivery
{
    public function deliver(SupportMessage $message): bool
    {
        $lock = Cache::lock('support:delivery:'.$message->id, 60);
        if (! $lock->get()) {
            return false;
        }
        try {
            $message->refresh();
            if ($message->delivery_status === 'sent') {
                return true;
            }
            $ticket = $message->ticket()->with(['client', 'session'])->firstOrFail();
            $chat = $ticket->client?->telegram_chat_id;
            $bot = app(TelegramSupportBotService::class);
            if (! $chat) {
                $result = ['ok' => false];
            } elseif ($source = data_get($message->payload, 'source_message_id')) {
                $result = $bot->copyMessage($chat, data_get($message->payload, 'source_chat_id'), (int) $source);
            } else {
                $result = $bot->sendMessage($chat, e($message->body));
            }
            $ok = ($result['ok'] ?? false) === true;
            DB::transaction(function () use ($message, $ticket, $result, $ok, $chat) {
                $message->forceFill([
                    'delivery_status' => $ok ? 'sent' : 'failed',
                    'delivery_error' => $ok ? null : 'Telegram не підтвердив доставку. Перевірте підключення та повторіть надсилання.',
                    'delivered_at' => $ok ? now() : null,
                    'telegram_message_id' => $ok ? data_get($result, 'result.message_id') : null, 'telegram_chat_id' => $chat,
                ])->save();
                if (! $ok) {
                    return;
                }
                $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                $ticket->forceFill([
                    'status' => $ticket->status === SupportTicket::STATUS_NEW ? SupportTicket::STATUS_IN_PROGRESS : $ticket->status,
                    'first_response_at' => $ticket->first_response_at ?: now(), 'last_staff_message_at' => now(),
                ])->save();
                $ticket->session?->forceFill([
                    'status' => $ticket->session->status === SupportSession::STATUS_CLOSED ? SupportSession::STATUS_CLOSED : SupportSession::STATUS_IN_PROGRESS,
                    'last_message_at' => now(),
                ])->save();
            });

            return $ok;
        } finally {
            $lock->release();
        }
    }
}
