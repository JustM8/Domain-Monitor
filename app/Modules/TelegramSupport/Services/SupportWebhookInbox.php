<?php

namespace App\Modules\TelegramSupport\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SupportWebhookInbox
{
    public function handle(array $payload, SupportUpdateHandler $handler, TelegramSupportBotService $bot): void
    {
        $key = ['bot' => 'support', 'update_id' => $payload['update_id']];
        DB::table('telegram_webhook_receipts')->insertOrIgnore($key + ['payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))]);
        // Persist the claim BEFORE external side effects; a claimed event is never blindly replayed.
        $claimed = DB::table('telegram_webhook_receipts')->where($key)->whereNull('processed_at')->where('status', 'pending')->update(['status' => 'processing', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $previousContext = $bot->handlingWebhook;
        $bot->handlingWebhook = true;
        try {
            $handler->handle($payload, $bot);
            DB::table('telegram_webhook_receipts')->where($key)->update(['status' => 'completed', 'processed_at' => now(), 'payload' => null]);
        } catch (\Throwable $e) {
            // Partial changes stay committed, and operators can reconcile the encrypted original event.
            DB::table('telegram_webhook_receipts')->where($key)->update(['status' => 'needs_review']);
        } finally {
            $bot->handlingWebhook = $previousContext;
        }
    }

    public function recoverInterrupted(): int
    {
        return DB::table('telegram_webhook_receipts')->where('bot', 'support')->where('status', 'processing')->where('started_at', '<', now()->subMinutes(15))->update(['status' => 'needs_review']);
    }
}
