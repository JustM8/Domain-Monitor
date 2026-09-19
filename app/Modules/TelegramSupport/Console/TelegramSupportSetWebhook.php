<?php

namespace App\Modules\TelegramSupport\Console;

use App\Modules\TelegramSupport\Services\TelegramSupportBotService;
use Illuminate\Console\Command;

class TelegramSupportSetWebhook extends Command
{
    protected $signature = 'telegram-support:set-webhook';

    protected $description = 'Register Telegram webhook for the support bot';

    public function handle(TelegramSupportBotService $bot): int
    {
        if (strlen((string) config('telegram_support.webhook_secret')) < 32) {
            $this->error('Set a random webhook secret (at least 32 characters) before registering.');

            return self::FAILURE;
        }
        $webhookUrl = (string) (config('telegram_support.webhook_url') ?: rtrim(config('app.url'), '/').'/api/telegram/support/webhook');

        if (blank($webhookUrl)) {
            $this->error('TELEGRAM_SUPPORT_WEBHOOK_URL is not set.');

            return self::FAILURE;
        }

        $response = $bot->setWebhook($webhookUrl, config('telegram_support.webhook_secret'));

        if (($response['ok'] ?? false) === true) {
            $this->info('Telegram support webhook registered.');

            return self::SUCCESS;
        }

        $this->error($response['description'] ?? 'Unable to register Telegram support webhook.');

        return self::FAILURE;
    }
}
