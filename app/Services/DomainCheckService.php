<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Check;
use Throwable;

class DomainCheckService
{
    public function check(Domain $domain): void
    {
        $url = trim($domain->domain);

        if (!str_starts_with($url, 'http')) {
            $url = 'http://' . $url;
        }

        $url = rtrim($url, '/');
        $start = microtime(true);

        try {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $domain->timeout ?? 5,
                CURLOPT_CONNECTTIMEOUT => $domain->timeout ?? 5,
                CURLOPT_FOLLOWLOCATION => true,
                // ✅ SSL fix
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            if ($domain->method === 'HEAD') {
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
            }

            curl_exec($ch);

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            $time = round((microtime(true) - $start), 3);

            Check::create([
                'domain_id'     => $domain->id,
                'checked_at'    => now(),
                'status_code'   => $status ?: null,
                'response_time' => $time,
                'is_success'    => $status >= 200 && $status < 400,
                'error'         => $error ?: null,
            ]);

        } catch (Throwable $e) {
            Check::create([
                'domain_id'  => $domain->id,
                'checked_at' => now(),
                'is_success' => false,
                'error'      => $e->getMessage(),
            ]);
        }

        $domain->update([
            'last_checked_at' => now(),
        ]);
    }
}
