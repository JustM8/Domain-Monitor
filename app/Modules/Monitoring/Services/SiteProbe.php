<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Shared\Http\SafeHttp;
use App\Modules\Site\Models\Site;
use Carbon\CarbonImmutable;
use GuzzleHttp\TransferStats;

class SiteProbe
{
    public function __construct(private SafeHttp $http) {}

    public function run(Site $site): array
    {
        $started = microtime(true);
        $timings = [];
        $expires = null;
        $url = $site->monitoring_url ?: $site->url;
        $result = ['http_status' => null, 'error' => null, 'error_kind' => null, 'checked_url' => $url];
        try {
            $response = $this->http->get($url, $site->monitoring_timeout, function (TransferStats $stats) use (&$timings, &$expires) {
                $data = $stats->getHandlerStats();
                foreach (['namelookup_time', 'connect_time', 'appconnect_time', 'starttransfer_time', 'total_time'] as $key) {
                    if (isset($data[$key])) {
                        $timings[$key] = ($timings[$key] ?? 0) + (int) round($data[$key] * 1000);
                    }
                }
                // Certificate belongs to the last HTTPS response. Never expose certificate contents.
                $expires = null;
                $expiry = $data['certinfo'][0]['Expire date'] ?? null;
                if ($expiry && strtotime($expiry) !== false) {
                    $expires = CarbonImmutable::createFromTimestamp(strtotime($expiry), 'UTC')->toDateTimeString();
                }
            });
            $result['http_status'] = $response->status();
            $up = $site->monitoring_status_codes
                ? in_array($response->status(), $site->monitoring_status_codes, true)
                : $response->successful();
            if (! $up) {
                $result['error'] = 'HTTP '.$response->status();
                $result['error_kind'] = 'http';
            } elseif ($site->monitoring_content && ! str_contains($response->body(), $site->monitoring_content)) {
                $up = false;
                $result['error'] = 'У відповіді немає очікуваного тексту.';
                $result['error_kind'] = 'content';
            }
            $result['technical_availability'] = $up ? 'up' : 'down';
        } catch (\Throwable $e) {
            $kind = match (true) {
                str_contains($e->getMessage(), 'DNS'), str_contains($e->getMessage(), 'cURL error 6:') => 'dns',
                preg_match('/cURL error (35|51|60):/', $e->getMessage()) === 1 => 'tls',
                str_contains($e->getMessage(), 'cURL error 28:') => 'timeout',
                str_contains($e->getMessage(), 'cURL error 7:') => 'connect',
                str_contains($e->getMessage(), 'перенаправлень') => 'redirect',
                str_contains($e->getMessage(), '2 МБ') => 'size',
                $e instanceof \InvalidArgumentException => 'policy',
                default => 'connection',
            };
            $result['technical_availability'] = 'down';
            $result['error_kind'] = $kind;
            $result['error'] = [
                'dns' => 'Не вдалося визначити DNS-адресу.', 'tls' => 'Помилка перевірки TLS.',
                'timeout' => 'Перевищено час очікування.', 'connect' => 'Немає з’єднання із сервером.',
                'redirect' => 'Забагато перенаправлень.', 'size' => 'Відповідь перевищує 2 МБ.',
                'policy' => 'Адреса не дозволена політикою вихідних запитів.',
                'connection' => 'Помилка з’єднання або отримання відповіді.',
            ][$kind];
        }

        return $result + [
            'availability' => $result['technical_availability'],
            'response_ms' => (int) round((microtime(true) - $started) * 1000),
            'timings' => $timings ? json_encode($timings) : null,
            'certificate_expires_at' => $expires,
        ];
    }
}
