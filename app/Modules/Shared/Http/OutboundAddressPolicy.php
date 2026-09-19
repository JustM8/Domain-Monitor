<?php

namespace App\Modules\Shared\Http;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class OutboundAddressPolicy
{
    public function target(string $url, bool $control = false, bool $allowInternal = true): array
    {
        $parts = parse_url($url);
        if (! $parts || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(strtolower($parts['scheme'] ?? ''), $control ? ['https'] : ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Потрібна коректна HTTP(S)-адреса без пароля або фрагмента.');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Дозволені лише порти 80 та 443.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if (! $addresses) {
            throw new InvalidArgumentException('DNS-адресу не знайдено.');
        }
        $origin = strtolower($parts['scheme']).'://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $exceptions = config('outbound.internal_targets', []);
        $allowed = is_array($exceptions) ? ($exceptions[$origin] ?? []) : [];
        foreach ($addresses as $ip) {
            $internal = ! $control && $allowInternal && is_array($allowed) && in_array($ip, $allowed, true)
                && IpUtils::checkIp($ip, ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7']);
            if (! $this->isPublic($ip) && ! $internal) {
                throw new InvalidArgumentException('Внутрішні та службові адреси заборонені.');
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: []))));
    }

    public function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Include shared, documentation, transition and mapped ranges not rejected by all PHP versions.
        return ! IpUtils::checkIp($ip, [
            '0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '192.0.0.0/24', '192.0.2.0/24',
            '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            '::/96', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8',
        ]);
    }
}
