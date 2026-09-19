<?php

namespace App\Modules\TelegramAccess\Console;

use App\Modules\TelegramAccess\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register Telegram webhook for the portal bot';

    public function handle(TelegramBotService $telegram): int
    {
        if (strlen((string) config('telegram_access.webhook_secret')) < 32) {
            $this->error('Set a random webhook secret (at least 32 characters) before registering.');

            return self::FAILURE;
        }
        $webhookUrl = (string) (config('telegram_access.webhook_url') ?: rtrim(config('app.url'), '/').'/api/telegram/webhook');

        if (blank($webhookUrl)) {
            $this->error('TELEGRAM_WEBHOOK_URL is not set.');

            return self::FAILURE;
        }

        $response = $telegram->setWebhook($webhookUrl, config('telegram_access.webhook_secret'));

        if (($response['ok'] ?? false) === true) {
            $this->info('Telegram webhook registered.');

            return self::SUCCESS;
        }

        $this->error($response['description'] ?? 'Unable to register Telegram webhook.');

        return self::FAILURE;
    }
}
