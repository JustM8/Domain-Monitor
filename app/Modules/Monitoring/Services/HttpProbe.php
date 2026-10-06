<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Shared\Http\SafeHttp;
use GuzzleHttp\TransferStats;

class HttpProbe
{
    public function __construct(private MonitoringAddressPolicy $addresses) {}

    public function run(string $url, array $config, int $timeout): array
    {
        $start = hrtime(true);
        $cert = null;
        $status = null;
        $kind = null;
        $this->addresses->budget($start / 1e9 + $timeout);
        $http = new SafeHttp($this->addresses);
        try {
            $response = $http->get($url, $timeout, function (TransferStats $stats) use (&$cert, $url) {
                if (! $stats->hasResponse() || $stats->getHandlerErrorData()) {
                    return;
                }
                $uri = $stats->getEffectiveUri();
                $target = strtolower($uri->getHost()).':'.($uri->getPort() ?? 443);
                $configured = strtolower(parse_url($url, PHP_URL_HOST)).':'.(parse_url($url, PHP_URL_PORT) ?? 443);
                if ($uri->getScheme() !== 'https' || $target !== $configured) {
                    return;
                }
                $cert = null;
                $pem = $stats->getHandlerStats()['certinfo'][0]['Cert'] ?? null;
                if (! $pem) {
                    return;
                }
                $parsed = @openssl_x509_parse($pem);
                $fingerprint = @openssl_x509_fingerprint($pem, 'sha256');
                if ($parsed && $fingerprint && isset($parsed['validFrom_time_t'], $parsed['validTo_time_t'])) {
                    $cert = ['target_key' => hash('sha256', $target), 'fingerprint' => $fingerprint,
                        'not_before' => MonitoringTime::store(\Carbon\CarbonImmutable::createFromTimestamp($parsed['validFrom_time_t'], 'UTC')),
                        'not_after' => MonitoringTime::store(\Carbon\CarbonImmutable::createFromTimestamp($parsed['validTo_time_t'], 'UTC'))];
                }
            }, true);
            $status = $response->status();
            $ok = ! empty($config['status_codes']) ? in_array($status, $config['status_codes'], true) : $response->successful();
            if (! $ok) {
                $kind = 'http';
            } elseif (! empty($config['content']) && ! str_contains($response->body(), $config['content'])) {
                $ok = false;
                $kind = 'content';
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && (str_contains($e->getMessage(), 'requires proc_open') || str_contains($e->getMessage(), 'worker'))) {
                throw $e;
            }
            $ok = false;
            $kind = match (true) {
                $e instanceof \InvalidArgumentException => 'policy', preg_match('/cURL error (35|51|60):/', $e->getMessage()) === 1 => 'tls',
                str_contains($e->getMessage(), 'timeout'), str_contains($e->getMessage(), 'cURL error 28:') => 'timeout', str_contains($e->getMessage(), 'DNS'), str_contains($e->getMessage(), 'cURL error 6:') => 'dns', default => 'connection'
            };
        }

        return ['up' => $ok, 'error_kind' => $kind, 'http_status' => $status, 'response_ms' => (int) round((hrtime(true) - $start) / 1000000), 'certificate' => $cert];
    }
}
