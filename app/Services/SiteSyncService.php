<?php

namespace App\Services;

use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SiteSyncService
{
    public function sync(Site $site, string $state, array $payload = []): array
    {
        $endpoint = $this->buildEndpoint($site);
        $context = [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'site_url' => $site->url,
            'state' => $state,
            'endpoint' => $endpoint,
            'payload_keys' => array_keys($payload),
        ];

        if ($endpoint === null) {
            Log::warning('site.sync.skipped', $context + [
                'reason' => 'Unable to build child sync endpoint.',
            ]);

            return $this->storeFailure($site, 'Unable to build child sync endpoint.');
        }

        $token = $this->decryptToken($site);

        if ($token === null) {
            Log::warning('site.sync.skipped', $context + [
                'reason' => 'API token is missing or invalid.',
            ]);

            return $this->storeFailure($site, 'API token is missing or invalid.');
        }

        Log::info('site.sync.started', $context);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->retry(1, 250)
                ->withHeaders([
                    'X-Site-Token' => $token,
                ])
                ->post($endpoint, array_merge([
                    'site_id' => $site->id,
                    'state' => $state,
                    'portal_url' => rtrim((string) config('app.url'), '/'),
                    'site_url' => $site->url,
                ], $payload));

            $bodyStatus = (string) data_get($response->json(), 'status', '');
            $ok = $response->successful() && in_array($bodyStatus, ['active', 'disabled'], true);

            $site->forceFill([
                'last_synced_at' => now(),
                'last_sync_status' => $ok ? $bodyStatus : 'failed',
                'last_sync_error' => $ok
                    ? null
                    : $this->buildErrorMessage($response->status(), $response->body(), $bodyStatus),
            ])->saveQuietly();

            if ($ok) {
                Log::info('site.sync.succeeded', $context + [
                    'http_status' => $response->status(),
                    'response_status' => $bodyStatus,
                ]);
            } else {
                Log::warning('site.sync.failed', $context + [
                    'http_status' => $response->status(),
                    'response_status' => $bodyStatus,
                    'response_excerpt' => $this->shortenBody($response->body()),
                ]);
            }

            return [
                'ok' => $ok,
                'status' => $ok ? $bodyStatus : 'failed',
                'message' => $ok ? 'Child site synced successfully.' : 'Child site sync failed.',
            ];
        } catch (Throwable $e) {
            $site->forceFill([
                'last_synced_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_error' => $e->getMessage(),
            ])->saveQuietly();

            Log::error('site.sync.exception', $context + [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function buildEndpoint(Site $site): ?string
    {
        $baseUrl = trim((string) $site->url);

        if ($baseUrl === '') {
            return null;
        }

        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'http://' . $baseUrl;
        }

        return rtrim($baseUrl, '/') . '/api/portal/sync';
    }

    protected function decryptToken(Site $site): ?string
    {
        if (! $site->api_token) {
            return null;
        }

        try {
            return Crypt::decryptString($site->api_token);
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildErrorMessage(int $statusCode, string $body, string $bodyStatus): string
    {
        $title = $this->extractHtmlTitle($body);

        if ($title !== null) {
            return $bodyStatus !== '' ? $bodyStatus . ': ' . $title : $title;
        }

        $message = trim(strip_tags($body));
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        if ($message === '') {
            $message = 'HTTP ' . $statusCode;
        }

        $message = mb_substr($message, 0, 220);

        return $bodyStatus !== '' ? $bodyStatus . ': ' . $message : $message;
    }

    protected function shortenBody(string $body): string
    {
        $body = trim(strip_tags($body));

        if ($body === '') {
            return '';
        }

        return mb_substr($body, 0, 500);
    }

    protected function extractHtmlTitle(string $body): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return null;
        }

        $title = html_entity_decode(strip_tags((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s+/', ' ', trim($title)) ?? '';

        return $title !== '' ? $title : null;
    }

    protected function storeFailure(Site $site, string $message): array
    {
        $message = mb_substr(trim($message), 0, 220);

        $site->forceFill([
            'last_synced_at' => now(),
            'last_sync_status' => 'failed',
            'last_sync_error' => $message,
        ])->saveQuietly();

        Log::warning('site.sync.failed', [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'site_url' => $site->url,
            'reason' => $message,
        ]);

        return [
            'ok' => false,
            'status' => 'failed',
            'message' => $message,
        ];
    }
}
