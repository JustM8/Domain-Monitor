<?php

namespace App\Modules\Monitoring\Services;

use Illuminate\Support\Facades\DB;

final class MonitorHeartbeat
{
    public function rotate(int $id, int $overlap = 0): string
    {
        return DB::transaction(function () use ($id, $overlap) {
            $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first() ?? throw new \RuntimeException('Monitoring record missing');
            if ($m->type !== 'heartbeat') {
                throw new \InvalidArgumentException('Not a heartbeat');
            }
            $at = MonitoringTime::now();
            // At most one previous usable credential, even through repeated rotation.
            DB::table('monitoring_heartbeat_credentials')->where('monitor_id', $id)->whereNotNull('expires_at')->delete();
            DB::table('monitoring_heartbeat_credentials')->where('monitor_id', $id)->update(['expires_at' => MonitoringTime::store($at->addSeconds(max(0, min(3600, $overlap))))]);
            $secret = bin2hex(random_bytes(32));
            $public = bin2hex(random_bytes(12));
            DB::table('monitoring_heartbeat_credentials')->insert(['monitor_id' => $id, 'public_token_id' => $public, 'secret_hash' => hash('sha256', $secret), 'created_at' => MonitoringTime::store($at)]);

            return $public.'.'.$secret;
        });
    }

    public function revoke(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first() ?? throw new \RuntimeException('Monitoring record missing');
            DB::table('monitoring_heartbeat_credentials')->where('monitor_id', $id)->update(['revoked_at' => MonitoringTime::store(MonitoringTime::now())]);
        });
    }

    public function accept(string $credential, ?string $job = null): bool
    {
        MonitoringTime::session();
        if (! preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $credential, $match)) {
            return false;
        }
        $lookup = DB::table('monitoring_heartbeat_credentials')->where('public_token_id', $match[1])->first();
        if (! $lookup) {
            return false;
        }

        return DB::transaction(function () use ($lookup, $match, $job) {
            $m = DB::table('monitoring_monitors')->where('id', $lookup->monitor_id)->lockForUpdate()->first();
            $s = DB::table('monitoring_states')->where('monitor_id', $lookup->monitor_id)->lockForUpdate()->first();
            $key = DB::table('monitoring_heartbeat_credentials')->where('id', $lookup->id)->first();
            $at = MonitoringTime::now();
            if (! $key || $key->revoked_at || ($key->expires_at && MonitoringTime::parse($key->expires_at)->lte($at)) || ! hash_equals($key->secret_hash, hash('sha256', $match[2]))
                || ! $m || ! $m->enabled || $m->type !== 'heartbeat' || ! $s->period_id || ! DB::table('sites')->where('id', $m->site_id)->whereNull('deleted_at')->exists()) {
                return false;
            }
            if ($job !== null && $job === $s->last_job_run_id) {
                return true;
            }
            app(MonitorHistory::class)->flush($m, $s, $at);
            app(MonitorRunner::class)->close($m, $s, $at, 'recovered', true);
            $c = json_decode($m->config, true);
            $next = $at->addSeconds($c['expected_interval_seconds']);
            $deadline = $next->addSeconds($c['grace_seconds']);
            DB::table('monitoring_states')->where('monitor_id', $m->id)->update(['availability' => 'up', 'phase' => null, 'last_ping_at' => MonitoringTime::store($at), 'last_observed_at' => MonitoringTime::store($at),
                'next_expected_at' => MonitoringTime::store($next), 'deadline_at' => MonitoringTime::store($deadline), 'next_due_at' => MonitoringTime::store($deadline), 'valid_until' => MonitoringTime::store($deadline), 'last_job_run_id' => $job, 'active_incident_id' => null]);

            return true;
        });
    }

    public function evaluate(int $limit = 100): int
    {
        $ids = DB::table('monitoring_states as s')->join('monitoring_monitors as m', 'm.id', '=', 's.monitor_id')->where('m.type', 'heartbeat')->where('m.enabled', true)
            ->where('s.next_due_at', '<=', MonitoringTime::store(MonitoringTime::now()))->orderBy('s.next_due_at')->limit($limit)->pluck('m.id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first();
                $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first();
                $at = MonitoringTime::now();
                if (! $m->enabled || ! $s->period_id || ! $s->deadline_at || MonitoringTime::parse($s->deadline_at)->gt($at)) {
                    return;
                }
                app(MonitorHistory::class)->flush($m, $s, $at);
                if (! $s->active_incident_id) {
                    app(MonitorRunner::class)->open($m, $s, $at, $s->deadline_at, 'heartbeat_missed', []);
                }
                DB::table('monitoring_states')->where('monitor_id', $id)->update(['availability' => 'down', 'phase' => 'heartbeat_missed', 'last_evaluated_at' => MonitoringTime::store($at),
                    'valid_until' => MonitoringTime::store($at->addSeconds(120)), 'next_due_at' => MonitoringTime::store($at->addSeconds(60)), 'active_incident_id' => $s->active_incident_id]);
            });
        }

        return count($ids);
    }
}
