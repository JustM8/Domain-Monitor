<?php

namespace App\Modules\TelegramAccess\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function enabled(): bool
    {
        return filled(config('telegram_access.bot_token'));
    }

    protected function pendingRequest(bool $json = true): PendingRequest
    {
        $pending = $json ? Http::asJson() : Http::baseUrl('https://api.telegram.org');

        return $pending->connectTimeout(5)->timeout(15)->withOptions(['verify' => true]);
    }

    public function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('telegram_access.bot_token').'/'.ltrim($method, '/');
    }

    protected function requestWithClient(string $method, PendingRequest $request, array $payload = []): array
    {
        if (! $this->enabled()) {
            Log::warning('telegram_access.api.disabled', [
                'method' => $method,
                'payload_keys' => array_keys($payload),
            ]);

            return ['ok' => false, 'description' => 'Telegram bot token is missing.'];
        }

        try {
            $response = $request->post($this->apiUrl($method), $payload);
            $data = $response->json() ?? [];

            Log::debug('telegram_access.api.request', [
                'method' => $method,
                'status' => $response->status(),
                'ok' => (bool) data_get($data, 'ok'),
                'description' => data_get($data, 'description'),
                'payload_keys' => array_keys($payload),
            ]);

            if (! $response->successful() || ! data_get($data, 'ok')) {
                Log::warning('telegram_access.api.failed', [
                    'method' => $method,
                    'status' => $response->status(),
                    'ok' => (bool) data_get($data, 'ok'),
                    'description' => data_get($data, 'description'),
                    'error_code' => data_get($data, 'error_code'),
                    'payload_keys' => array_keys($payload),
                ]);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('telegram_access.api.exception', [
                'method' => $method,
                'exception' => $e::class,
                'payload_keys' => array_keys($payload),
            ]);

            return ['ok' => false, 'description' => 'Telegram transport failed.'];
        }
    }

    public function request(string $method, array $payload = []): array
    {
        return $this->requestWithClient($method, $this->pendingRequest(true), $payload);
    }

    public function sendMessage(string|int $chatId, string $text, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId)) {
            return ['ok' => false, 'description' => 'Telegram bot is disabled or chat ID is empty.'];
        }

        if (mb_strlen($text) > 3800) {
            // Plain text chunks avoid broken HTML tags or entities across Telegram's limit.
            $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $result = ['ok' => true];
            foreach (mb_str_split($plain, 3500) as $chunk) {
                $result = $this->request('sendMessage', array_merge(['chat_id' => (string) $chatId, 'text' => $chunk, 'protect_content' => true, 'disable_web_page_preview' => true], $options, ['parse_mode' => null]));
                if (! ($result['ok'] ?? false)) {
                    return $result;
                }
            }

            return $result;
        }

        return $this->request('sendMessage', array_merge([
            'chat_id' => (string) $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'protect_content' => true,
            'disable_web_page_preview' => true,
        ], $options));
    }

    public function editMessageText(string|int $chatId, int $messageId, string $text, array $options = []): array
    {
        return $this->request('editMessageText', array_merge([
            'chat_id' => (string) $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'protect_content' => true,
            'disable_web_page_preview' => true,
        ], $options));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if (filled($text)) {
            $payload['text'] = $text;
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        return $this->request('answerCallbackQuery', $payload);
    }

    public function sendDocument(string|int $chatId, string $content, string $filename, array $options = []): array
    {
        if (! $this->enabled() || blank($chatId)) {
            return ['ok' => false, 'description' => 'Telegram bot is disabled or chat ID is empty.'];
        }

        $request = $this->pendingRequest(false)->attach('document', $content, $filename);

        return $this->requestWithClient('sendDocument', $request, array_merge([
            'chat_id' => (string) $chatId,
        ], $options));
    }

    public function setWebhook(string $url, ?string $secret = null): array
    {
        $payload = ['url' => $url];

        if (filled($secret)) {
            $payload['secret_token'] = $secret;
        }

        return $this->request('setWebhook', $payload);
    }

    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }
}
