<?php

namespace App\Modules\Monitoring\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MonitorRunner
{
    public function claim(int $id): ?array
    {
        MonitoringTime::session();

        return DB::transaction(function () use ($id) {
            $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first();
            $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first();
            $at = MonitoringTime::now();
            if (! $m || ! $s || ! $m->enabled || $m->type === 'heartbeat' || ! $s->period_id || ! $s->next_due_at || MonitoringTime::parse($s->next_due_at)->gt($at)
                || ($s->lease_until && MonitoringTime::parse($s->lease_until)->gt($at)) || ! DB::table('sites')->where('id', $m->site_id)->whereNull('deleted_at')->exists()) {
                return null;
            }
            $effective = app(MonitoringSettings::class)->effective($m);
            $token = Str::random(40);
            $sequence = $s->dispatch_sequence + 1;
            DB::table('monitoring_states')->where('monitor_id', $id)->update(['claim_token' => $token, 'dispatch_sequence' => $sequence,
                'captured_revision' => $m->revision, 'captured_checks_revision' => $effective['checks_revision'], 'lease_until' => MonitoringTime::store($at->addSeconds(120))]);

            return ['monitor' => $m, 'token' => $token, 'sequence' => $sequence, 'revision' => $m->revision, 'effective' => $effective, 'slot' => $s->next_due_at];
        });
    }

    public function probe(object $m, array $effective): array
    {
        $config = json_decode($m->config, true);
        if ($m->type === 'tcp') {
            return app(TcpProbe::class)->run($config, $effective['timeout']);
        }
        $site = DB::table('sites')->where('id', $m->site_id)->first();

        return app(HttpProbe::class)->run($config['url'] ?? $site->url, $config, $effective['timeout']);
    }

    public static function evidence(array $r): array
    {
        return array_intersect_key($r, array_flip(['up', 'error_kind', 'http_status', 'response_ms']));
    }

    private function planned(object $m): bool
    {
        $c = json_decode($m->config, true);
        if ($m->type !== 'http' || ! empty($c['url']) || ! ($c['respect_site_control'] ?? false)) {
            return false;
        }
        $site = DB::table('sites')->where('id', $m->site_id)->first();

        return ! $site->is_active && $site->confirmed_state === 'disabled' && $site->confirmed_control_version !== null && (int) $site->confirmed_control_version === (int) $site->control_version;
    }

    public function commit(array $claim, array $r): bool
    {
        return DB::transaction(function () use ($claim, $r) {
            $m = DB::table('monitoring_monitors')->where('id', $claim['monitor']->id)->lockForUpdate()->first();
            $s = DB::table('monitoring_states')->where('monitor_id', $m->id)->lockForUpdate()->first();
            $at = MonitoringTime::now();
            $currentEffective = app(MonitoringSettings::class)->effective($m);
            $capturedEffective = $claim['effective'];
            unset($currentEffective['checks_revision'], $capturedEffective['checks_revision']);
            if ($s->claim_token !== $claim['token'] || (int) $s->dispatch_sequence !== $claim['sequence'] || (int) $m->revision !== (int) $claim['revision']
                || $currentEffective !== $capturedEffective || ! $m->enabled || ! $s->period_id
                || ! $s->lease_until || MonitoringTime::parse($s->lease_until)->lte($at)) {
                return false;
            }
            $e = $claim['effective'];
            $history = app(MonitorHistory::class);
            $history->flush($m, $s, $at);
            $signature = hash('sha256', ($r['error_kind'] ?? '').':'.($r['http_status'] ?? ''));
            $fresh = $s->valid_until && MonitoringTime::parse($s->valid_until)->gte($at);
            // Candidate counters have a bounded retry window independent of public
            // observation freshness (a custom 60s cadence may use a 300s retry).
            if ($s->phase && $s->last_observed_at) {
                $fresh = MonitoringTime::parse($s->last_observed_at)->addSeconds(max(2 * $e['interval'], 2 * $e['retry']))->gte($at);
            }
            $s->failures = $fresh ? $s->failures : 0;
            $s->successes = $fresh ? $s->successes : 0;
            $planned = $this->planned($m);
            $state = 'unknown';
            $phase = null;
            if ($planned) {
                $state = 'planned';
                $this->close($m, $s, $at, 'planned', false);
                $s->failures = 0;
                $s->successes = 0;
                $s->first_failed_at = null;
            } elseif ($r['up']) {
                $s->failures = 0;
                $s->first_failed_at = null;
                $s->successes++;
                if ($s->active_incident_id && $s->successes < $e['recoveries']) {
                    $phase = 'confirming_recovery';
                } else {
                    $state = 'up';
                    $this->close($m, $s, $at, 'recovered', true);
                }
            } else {
                $s->successes = 0;
                $s->failures++;
                if ($s->failures === 1) {
                    $s->first_failed_at = MonitoringTime::store($at);
                    $s->candidate_evidence = json_encode(self::evidence($r));
                }
                if ($s->active_incident_id || $s->failures >= $e['failures']) {
                    $state = 'down';
                    if (! $s->active_incident_id) {
                        $this->open($m, $s, $at, $s->first_failed_at, $r['error_kind'] ?? 'failure', self::evidence($r));
                    }
                } else {
                    $phase = 'confirming_failure';
                }
                $latest = DB::table('monitoring_diagnostics')->where('monitor_id', $m->id)->where('kind', 'failed')->latest('id')->first();
                $selected = $s->failures <= $e['failures'] || $signature !== $s->error_signature || ! $latest || MonitoringTime::parse($latest->recorded_at)->lte($at->subHours(3));
                $history->diagnostic($m, $s, 'failed', self::evidence($r), $at, $selected);
            }
            if ($r['up'] && ! $planned && $r['response_ms'] !== null) {
                $history->sample($m->id, $r['response_ms'], $at);
            }
            if ($m->type === 'http') {
                app(MonitorCertificates::class)->observe($m, $s, $r['certificate'] ?? null, $at, $r['error_kind']);
            }
            $due = MonitoringTime::parse($claim['slot']);
            $steps = max(1, (int) floor($due->diffInSeconds($at, false) / $e['interval']) + 1);
            $next = $phase ? $at->addSeconds($e['retry']) : $due->addSeconds($steps * $e['interval']);
            DB::table('monitoring_states')->where('monitor_id', $m->id)->update(['availability' => $state, 'phase' => $phase, 'failures' => $s->failures, 'successes' => $s->successes,
                'first_failed_at' => $s->first_failed_at, 'candidate_evidence' => $s->candidate_evidence, 'active_incident_id' => $s->active_incident_id, 'last_observed_at' => MonitoringTime::store($at), 'valid_until' => MonitoringTime::store($at->addSeconds(2 * $e['interval'])),
                'next_due_at' => MonitoringTime::store($next), 'claim_token' => null, 'lease_until' => null, 'response_ms' => $r['response_ms'], 'http_status' => $r['http_status'], 'error_kind' => $r['error_kind'], 'error_signature' => $signature]);

            return true;
        });
    }

    public function open(object $m, object $s, CarbonImmutable $at, string $start, string $cause, array $evidence): void
    {
        $s->active_incident_id = DB::table('monitoring_incidents')->insertGetId(['monitor_id' => $m->id, 'generation' => $s->generation, 'started_at' => $start,
            'detected_at' => MonitoringTime::store($at), 'cause' => $cause, 'evidence' => json_encode(['first' => json_decode($s->candidate_evidence ?? 'null', true) ?? $evidence, 'confirmation' => $evidence])]);
        app(MonitorEvents::class)->emit($m, $s, $m->type === 'heartbeat' ? 'heartbeat_missed' : 'monitor_down', $at, ['cause' => $cause, 'evidence' => $evidence, 'started_at' => $start]);
    }

    public function close(object $m, object $s, CarbonImmutable $at, string $reason, bool $recovery): void
    {
        if (! $s->active_incident_id) {
            return;
        }
        $incident = DB::table('monitoring_incidents')->where('id', $s->active_incident_id)->first();
        DB::table('monitoring_incidents')->where('id', $s->active_incident_id)->update(['closed_at' => MonitoringTime::store($at), 'close_reason' => $reason, 'recovered_at' => $recovery ? MonitoringTime::store($at) : null]);
        if ($recovery) {
            app(MonitorEvents::class)->emit($m, $s, $m->type === 'heartbeat' ? 'heartbeat_recovered' : 'monitor_recovered', $at, ['cause' => $incident->cause, 'started_at' => $incident->started_at, 'detected_at' => $incident->detected_at]);
        }
        $s->active_incident_id = null;
    }

    public function manual(int $id): array
    {
        $m = DB::table('monitoring_monitors')->where('id', $id)->first() ?? throw new \RuntimeException('Monitoring record missing');
        if ($m->type === 'heartbeat') {
            throw new \InvalidArgumentException('Heartbeat accepts producer completion only.');
        }
        DB::transaction(function () use ($id) {
            DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first();
            $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first();
            $day = MonitoringTime::now()->setTimezone(MonitoringTime::CALENDAR)->toDateString();
            $count = $s->manual_day === $day ? $s->manual_attempts : 0;
            if ($count >= 20) {
                throw new \RuntimeException('Manual daily cap reached.');
            }
            DB::table('monitoring_states')->where('monitor_id', $id)->update(['manual_day' => $day, 'manual_attempts' => $count + 1]);
        });
        $r = $this->probe($m, app(MonitoringSettings::class)->effective($m));
        $r['persisted'] = DB::transaction(function () use ($id, $m, $r) {
            $current = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first();
            $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first();

            return $current && $current->revision === $m->revision ? app(MonitorHistory::class)->diagnostic($m, $s, 'manual', self::evidence($r), MonitoringTime::now()) : false;
        });

        return $r;
    }

    public function run(int $limit = 100, ?float $deadline = null): int
    {
        $at = MonitoringTime::store(MonitoringTime::now());
        $ids = DB::table('monitoring_states as s')->join('monitoring_monitors as m', 'm.id', '=', 's.monitor_id')->join('sites', 'sites.id', '=', 'm.site_id')
            ->where('m.enabled', true)->where('m.type', '!=', 'heartbeat')->whereNull('sites.deleted_at')->whereNotNull('s.period_id')->where('s.next_due_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('s.lease_until')->orWhere('s.lease_until', '<=', $at))->orderBy('s.next_due_at')->orderBy('s.monitor_id')->limit($limit)->pluck('s.monitor_id');
        $n = 0;
        foreach ($ids as $id) {
            if ($deadline && microtime(true) > $deadline - 15) {
                break;
            }
            if ($claim = $this->claim($id)) {
                $n += $this->commit($claim, $this->probe($claim['monitor'], $claim['effective'])) ? 1 : 0;
            }
        }

        return $n;
    }
}
