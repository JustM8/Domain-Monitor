<?php

namespace App\Modules\Monitoring\Services;

class TcpProbe extends MonitoringAddressPolicy
{
    public function targetTcp(string $host, int $port): array
    {
        if (! in_array($port, app(MonitoringSettings::class)->ports(), true) || ! preg_match('/^[a-zA-Z0-9.:\-]+$/', $host)) {
            throw new \InvalidArgumentException('TCP policy');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if (! $ips) {
            throw new \InvalidArgumentException('DNS unavailable');
        }
        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new \InvalidArgumentException('Nonpublic TCP target');
            }
        }

        return ['ip' => $ips[0], 'port' => $port];
    }

    protected function connect(string $numeric, int $port, float $timeout): bool
    {
        $socket = @stream_socket_client('tcp://'.(str_contains($numeric, ':') ? '['.$numeric.']' : $numeric).':'.$port, $errno, $error, $timeout, STREAM_CLIENT_CONNECT);
        if (! $socket) {
            return false;
        } fclose($socket);

        return true;
    }

    public function run(array $config, int $timeout): array
    {
        $start = hrtime(true);
        $this->budget($start / 1e9 + $timeout);
        try {
            $t = $this->targetTcp($config['hostname'], (int) $config['port']);
            $remaining = $timeout - (hrtime(true) - $start) / 1e9;
            if ($remaining <= 0) {
                throw new \RuntimeException('Monitoring DNS timeout');
            } $ok = $this->connect($t['ip'], $t['port'], $remaining);
            $kind = $ok ? null : 'connect';
        } catch (\InvalidArgumentException) {
            $ok = false;
            $kind = 'policy';
        } catch (\RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'timeout')) {
                throw $e;
            } $ok = false;
            $kind = 'timeout';
        }

        return ['up' => $ok, 'error_kind' => $kind, 'http_status' => null, 'response_ms' => (int) round((hrtime(true) - $start) / 1000000), 'certificate' => null];
    }
}
