<?php

namespace App\Modules\Shared\Http;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SafeHttp
{
    public function __construct(private OutboundAddressPolicy $addresses) {}

    public function get(string $url, int $timeout = 6, ?callable $onStats = null): Response
    {
        $origin = fn (string $u) => [strtolower(parse_url($u, PHP_URL_SCHEME) ?? ''), strtolower(parse_url($u, PHP_URL_HOST) ?? ''), parse_url($u, PHP_URL_PORT) ?? (strtolower(parse_url($u, PHP_URL_SCHEME) ?? '') === 'https' ? 443 : 80)];
        $initialOrigin = $origin($url);
        for ($hop = 0; $hop <= 3; $hop++) {
            $response = $this->request('GET', $url, [], [], $origin($url) === $initialOrigin, $timeout, $onStats);
            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }
            if (! $response->header('Location') || $hop === 3) {
                throw new RuntimeException('Забагато перенаправлень.');
            }
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
        }
        throw new RuntimeException('Не вдалося перевірити адресу.');
    }

    public function postControl(string $url, string $token, array $payload): Response
    {
        return $this->request('POST', $url, $payload, ['X-Site-Token' => $token]);
    }

    private function request(string $method, string $url, array $data = [], array $headers = [], bool $allowInternal = false, int $timeout = 6, ?callable $onStats = null): Response
    {
        $target = $this->addresses->target($url, $method === 'POST', $allowInternal);
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
        $options = [
            'allow_redirects' => false, 'verify' => true, 'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$ip], CURLOPT_FRESH_CONNECT => true, CURLOPT_CERTINFO => true],
            'progress' => function ($downloadTotal, $downloaded) {
                if ($downloadTotal > 2 * 1024 * 1024 || $downloaded > 2 * 1024 * 1024) {
                    throw new RuntimeException('Відповідь перевищує 2 МБ.');
                }
            },
        ];
        if ($onStats !== null) {
            $options['on_stats'] = $onStats;
        }
        $request = Http::withOptions($options)->connectTimeout(3)->timeout(max(2, min(10, $timeout)))
            ->withHeaders($headers + ['User-Agent' => 'Portal-Monitor/1.0', 'Accept-Encoding' => 'identity']);

        return $method === 'POST' ? $request->asJson()->post($url, $data) : $request->get($url);
    }
}
