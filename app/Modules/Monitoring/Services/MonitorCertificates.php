<?php

namespace App\Modules\Monitoring\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MonitorCertificates
{
    public function observe(object $m, object $s, ?array $cert, CarbonImmutable $at, ?string $error = null): void
    {
        $config = json_decode($m->config, true);
        $url = $config['url'] ?? DB::table('sites')->where('id', $m->site_id)->value('url');
        if (strtolower(parse_url($url, PHP_URL_SCHEME) ?? '') !== 'https') {
            return;
        }
        $target = strtolower(parse_url($url, PHP_URL_HOST)).':'.(parse_url($url, PHP_URL_PORT) ?? 443);
        $key = hash('sha256', $target);
        $old = DB::table('monitoring_certificates')->where('monitor_id', $m->id)->where('target_key', $key)->first();
        if (! $cert || ($cert['target_key'] ?? null) !== $key) {
            if ($old) {
                DB::table('monitoring_certificates')->where('id', $old->id)->update(['last_error' => $error ?? 'certinfo_unavailable']);
            }

            return;
        }
        $expires = MonitoringTime::parse($cert['not_after']);
        $changed = $old && $old->fingerprint !== $cert['fingerprint'];
        $renewal = $changed && $expires->gt(MonitoringTime::parse($old->not_after));
        $gen = $old ? $old->generation + ($changed ? 1 : 0) : 1;
        $values = ['fingerprint' => $cert['fingerprint'], 'not_before' => $cert['not_before'], 'not_after' => $cert['not_after'], 'verified_at' => MonitoringTime::store($at), 'generation' => $gen, 'last_error' => null,
            'warning_emitted' => $old && ! $changed ? $old->warning_emitted : false, 'critical_emitted' => $old && ! $changed ? $old->critical_emitted : false];
        DB::table('monitoring_certificates')->updateOrInsert(['monitor_id' => $m->id, 'target_key' => $key], $values);
        $settings = app(MonitoringSettings::class)->get();
        $events = app(MonitorEvents::class);
        if ($renewal && $settings->ssl_renewal_enabled) {
            $events->emit($m, $s, 'ssl_renewed', $at, ['cause' => MonitoringTime::display($cert['not_after'])]);
        }
        $alert = null;
        if ($expires->lte($at->addDays($settings->ssl_critical_days)) && ! $values['critical_emitted']) {
            $alert = 'critical';
        } elseif ($expires->lte($at->addDays($settings->ssl_warning_days)) && ! $values['warning_emitted'] && ! $values['critical_emitted']) {
            $alert = 'warning';
        }
        if ($alert) {
            $events->emit($m, $s, 'ssl_'.$alert, $at, ['cause' => 'Expires '.MonitoringTime::display($cert['not_after'])]);
            DB::table('monitoring_certificates')->where('monitor_id', $m->id)->where('target_key', $key)->update([$alert.'_emitted' => true]);
        }
    }
}
