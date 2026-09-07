<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSupportBotService
{
    public function enabled(): bool
    {
        return filled(config('services.telegram_support.bot_token'));
    }

    protected function pendingRequest()
    {
        $pending = Http::asJson();

        if (! filter_var(config('services.telegram_support.http_verify'), FILTER_VALIDATE_BOOL)) {
            $pending = $pending->withoutVerifying();
        }

        return $pending
            ->connectTimeout(10)
            ->timeout(15);
    }

    public function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot' . config('services.telegram_support.bot_token') . '/' . ltrim($method, '/');
    }

    public function request(string $method, array $payload = []): array
    {
        if (! $this->enabled()) {
            Log::warning('telegram_support.api.disabled', [
                'method' => $method,
                'payload_keys' => array_keys($payload),
            ]);

            return ['ok' => false, 'description' => 'Telegram support bot token is missing.'];
        }

        try {
            $response = $this->pendingRequest()->post($this->apiUrl($method), $payload);
            $data = $response->json() ?? [];

            Log::debug('telegram_support.api.request', [
                'method' => $method,
                'status' => $response->status(),
                'ok' => (bool) data_get($data, 'ok'),
                'description' => data_get($data, 'description'),
                'payload_keys' => array_keys($payload),
            ]);

            if (! $response->successful() || ! data_get($data, 'ok')) {
                Log::warning('telegram_support.api.failed', [
                    'method' => $method,
                    'status' => $response->status(),
                    'ok' => (bool) data_get($data, 'ok'),
                    'description' => data_get($data, 'description'),
                    'error_code' => data_get($data, 'error_code'),
                ]);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('telegram_support.api.exception', [
                'method' => $method,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    public function sendMessage(string|int $chatId, string $text, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId)) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing or invalid payload.'];
        }

        return $this->request('sendMessage', array_merge([
            'chat_id' => (string) $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $options));
    }

    public function copyMessage(string|int $chatId, string|int $fromChatId, int $messageId, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId) || blank($fromChatId) || $messageId <= 0) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing or invalid payload.'];
        }

        return $this->request('copyMessage', array_merge([
            'chat_id' => (string) $chatId,
            'from_chat_id' => (string) $fromChatId,
            'message_id' => $messageId,
        ], $options));
    }

    public function createForumTopic(string|int $chatId, string $name, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId) || blank($name)) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing or invalid payload.'];
        }

        return $this->request('createForumTopic', array_merge([
            'chat_id' => (string) $chatId,
            'name' => $name,
        ], $options));
    }

    public function closeForumTopic(string|int $chatId, int|string $messageThreadId, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId) || blank($messageThreadId)) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing or invalid payload.'];
        }

        return $this->request('closeForumTopic', array_merge([
            'chat_id' => (string) $chatId,
            'message_thread_id' => (int) $messageThreadId,
        ], $options));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, array $options = []): void
    {
        if (! $this->enabled() || blank($callbackQueryId)) {
            return;
        }

        $payload = array_merge([
            'callback_query_id' => $callbackQueryId,
        ], $options);

        if (filled($text)) {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    public function setWebhook(string $url, ?string $secret = null): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing.'];
        }

        $payload = ['url' => $url];

        if (filled($secret)) {
            $payload['secret_token'] = $secret;
        }

        return $this->request('setWebhook', $payload);
    }

    public function deleteWebhook(): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'description' => 'Telegram support bot token is missing.'];
        }

        return $this->request('deleteWebhook');
    }
}
