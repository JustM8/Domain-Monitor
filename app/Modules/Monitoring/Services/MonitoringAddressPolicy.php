<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Shared\Http\OutboundAddressPolicy;

// Only Monitoring uses this resolver; other outbound/control paths retain their contract.
class MonitoringAddressPolicy extends OutboundAddressPolicy
{
    private float $deadline = 0;

    public function budget(float $deadline): void
    {
        $this->deadline = $deadline;
    }

    protected function resolve(string $host): array
    {
        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
            throw new \InvalidArgumentException('DNS hostname policy');
        }
        if (! function_exists('proc_open')) {
            throw new \RuntimeException('Monitoring requires proc_open and PHP CLI for bounded DNS');
        }
        $deadline = $this->deadline ?: hrtime(true) / 1e9 + 6;
        if (hrtime(true) / 1e9 >= $deadline) {
            throw new \RuntimeException('Monitoring DNS timeout');
        }
        $pipes = [];
        $php = config('monitoring.php_cli') ?: (PHP_SAPI === 'cli' ? PHP_BINARY : PHP_BINDIR.'/php');
        $process = proc_open([$php, app_path('Modules/Monitoring/dns-resolve.php'), $host], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new \RuntimeException('Monitoring DNS worker unavailable');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        try {
            do {
                $output .= stream_get_contents($pipes[1]);
                if (strlen($output) > 16384) {
                    throw new \InvalidArgumentException('DNS response exceeds policy');
                }
                $status = proc_get_status($process);
                if (! $status['running']) {
                    $output .= stream_get_contents($pipes[1]);
                    break;
                }
                if (hrtime(true) / 1e9 >= $deadline) {
                    throw new \RuntimeException('Monitoring DNS timeout');
                }
                usleep(10000);
            } while (true);
            $addresses = json_decode($output, true);
            if (! is_array($addresses)) {
                throw new \RuntimeException('Monitoring DNS worker failed');
            }

            return $addresses;
        } finally {
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }
}
