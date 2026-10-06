<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Site\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MonitorManager
{
    public static function phase(int $id, int $interval): string
    {
        return MonitoringTime::store(MonitoringTime::now()->addSeconds(hexdec(substr(hash('sha256', (string) $id), 0, 7)) % $interval));
    }

    public function primary(Site $site, bool $enabled = true): ?object
    {
        if (! Schema::hasTable('monitoring_monitors')) {
            return null;
        }

        return DB::transaction(function () use ($site, $enabled) {
            DB::table('sites')->where('id', $site->id)->lockForUpdate()->first();
            $m = DB::table('monitoring_monitors')->where('site_id', $site->id)->where('slot', 'primary_http')->first();
            if (! $m) {
                $m = $this->create($site->id, ['name' => 'Primary HTTP', 'type' => 'http', 'enabled' => $enabled, 'slot' => 'primary_http', 'config' => ['url' => null, 'respect_site_control' => true]]);
            }

            return $m;
        });
    }

    public function initializeState(int $id, bool $enabled, CarbonImmutable $at): void
    {
        $m = DB::table('monitoring_monitors')->where('id', $id)->first();
        $c = json_decode($m->config, true);
        $period = $enabled ? DB::table('monitoring_periods')->insertGetId(['monitor_id' => $id, 'generation' => 1, 'started_at' => MonitoringTime::store($at)]) : null;
        $heartbeat = $m->type === 'heartbeat';
        $deadline = $heartbeat && $enabled ? MonitoringTime::store($at->addSeconds($c['expected_interval_seconds'] + $c['grace_seconds'])) : null;
        DB::table('monitoring_states')->insert(['monitor_id' => $id, 'generation' => 1, 'period_id' => $period, 'cursor_at' => MonitoringTime::store($at), 'detail_available_from' => MonitoringTime::store($at),
            'next_due_at' => $enabled ? ($deadline ?? self::phase($id, app(MonitoringSettings::class)->effective($m)['interval'])) : null,
            'deadline_at' => $deadline, 'next_expected_at' => $heartbeat && $enabled ? MonitoringTime::store($at->addSeconds($c['expected_interval_seconds'])) : null]);
    }

    public function validate(array $data): array
    {
        $data = Validator::make($data, ['name' => 'required|string|max:255', 'type' => 'required|in:http,tcp,heartbeat', 'enabled' => 'required|boolean',
            'custom_interval_seconds' => 'nullable|integer|between:60,86400', 'timeout_seconds' => 'nullable|integer|between:2,10', 'failure_threshold' => 'nullable|integer|between:1,5', 'recovery_threshold' => 'nullable|integer|between:1,5',
            'recipient_mode' => 'sometimes|in:inherit,explicit,mute', 'recipient_ids' => 'sometimes|array|max:100', 'config' => 'present|array', 'slot' => 'nullable|in:primary_http'])->validate();
        if (($data['slot'] ?? null) && $data['type'] !== 'http') {
            throw ValidationException::withMessages(['type' => 'Primary slot тільки HTTP.']);
        }
        $rules = match ($data['type']) {
            'http' => ['url' => 'nullable|url:http,https|max:255', 'status_codes' => 'nullable|array|max:50', 'status_codes.*' => 'integer|between:100,599', 'content' => 'nullable|string|max:255', 'respect_site_control' => 'sometimes|boolean'],
            'tcp' => ['hostname' => 'required|string|max:253|regex:/^[a-zA-Z0-9.:\-]+$/', 'port' => 'required|integer|between:1,65535'],
            'heartbeat' => ['expected_interval_seconds' => 'required|integer|between:60,31536000', 'grace_seconds' => 'required|integer|between:0,604800'],
        };
        $data['config'] = Validator::make($data['config'], $rules)->validate();
        if ($data['type'] === 'tcp' && ! in_array((int) $data['config']['port'], app(MonitoringSettings::class)->ports(), true)) {
            throw ValidationException::withMessages(['config.port' => 'Порт не дозволений адміністратором.']);
        }
        if ($data['type'] === 'heartbeat') {
            $data['custom_interval_seconds'] = null;
        }
        $data['recipient_ids'] = app(MonitoringSettings::class)->recipients($data['recipient_ids'] ?? []);

        return $data;
    }

    public function create(int $siteId, array $data): object
    {
        $data = $this->validate($data);

        return DB::transaction(function () use ($siteId, $data) {
            DB::table('sites')->where('id', $siteId)->whereNull('deleted_at')->lockForUpdate()->first() ?? throw new \RuntimeException('Active Site required');
            $ids = $data['recipient_ids'];
            unset($data['recipient_ids']);
            $data['config'] = json_encode($data['config']);
            $id = DB::table('monitoring_monitors')->insertGetId($data + ['site_id' => $siteId, 'activated_at' => MonitoringTime::store(MonitoringTime::now())]);
            foreach ($ids as $uid) {
                DB::table('monitoring_monitor_recipients')->insert(['monitor_id' => $id, 'user_id' => $uid]);
            }
            $this->initializeState($id, (bool) $data['enabled'], MonitoringTime::now());

            return DB::table('monitoring_monitors')->where('id', $id)->first();
        });
    }

    public function update(int $id, array $data): void
    {
        $data = $this->validate($data);
        DB::transaction(function () use ($id, $data) {
            $siteId = DB::table('monitoring_monitors')->where('id', $id)->value('site_id');
            DB::table('sites')->where('id', $siteId)->whereNull('deleted_at')->lockForUpdate()->first() ?? throw new \RuntimeException('Active Site required');
            $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first() ?? throw new \RuntimeException('Monitoring record missing');
            if ($data['type'] !== $m->type || ($data['slot'] ?? $m->slot) !== $m->slot) {
                throw ValidationException::withMessages(['type' => 'Тип і slot незмінні. Створіть інший Monitor.']);
            }
            $ids = $data['recipient_ids'];
            unset($data['recipient_ids'], $data['slot']);
            $this->reset($id, $data['enabled'] ? 'configuration_changed' : 'paused', false);
            $data['config'] = json_encode($data['config']);
            DB::table('monitoring_monitors')->where('id', $id)->update($data + ['revision' => $m->revision + 1]);
            DB::table('monitoring_monitor_recipients')->where('monitor_id', $id)->delete();
            foreach ($ids as $uid) {
                DB::table('monitoring_monitor_recipients')->insert(['monitor_id' => $id, 'user_id' => $uid]);
            }
            if ($data['enabled']) {
                $this->resume($id);
            }
        });
    }

    public function reset(int $id, string $reason, bool $resume = true): void
    {
        DB::transaction(function () use ($id, $reason, $resume) {
            $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first() ?? throw new \RuntimeException('Monitoring record missing');
            $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first() ?? throw new \RuntimeException('Monitoring record missing');
            $at = MonitoringTime::now();
            app(MonitorHistory::class)->flush($m, $s, $at);
            if ($s->active_incident_id) {
                DB::table('monitoring_incidents')->where('id', $s->active_incident_id)->update(['closed_at' => MonitoringTime::store($at), 'close_reason' => $reason]);
            }
            if ($s->period_id) {
                DB::table('monitoring_periods')->where('id', $s->period_id)->update(['ended_at' => MonitoringTime::store($at)]);
            }
            DB::table('monitoring_deliveries')->whereIn('event_id', DB::table('monitoring_events')->where('monitor_id', $id)->select('id'))->whereIn('status', ['pending', 'claimed', 'retry_wait'])->update(['status' => 'cancelled', 'terminal_at' => MonitoringTime::store($at), 'claim_token' => null]);
            DB::table('monitoring_monitors')->where('id', $id)->increment('revision');
            DB::table('monitoring_states')->where('monitor_id', $id)->update(['availability' => 'unknown', 'phase' => $reason, 'claim_token' => null, 'lease_until' => null, 'next_due_at' => null,
                'period_id' => null, 'active_incident_id' => null, 'valid_until' => null, 'first_failed_at' => null, 'failures' => 0, 'successes' => 0, 'candidate_evidence' => null,
                'last_ping_at' => null, 'last_job_run_id' => null, 'last_observed_at' => null, 'deadline_at' => null, 'next_expected_at' => null, 'last_evaluated_at' => null, 'response_ms' => null, 'http_status' => null, 'error_kind' => null, 'error_signature' => null]);
            if ($resume && $m->enabled && ($s->period_id || $reason === 'resumed') && DB::table('sites')->where('id', $m->site_id)->whereNull('deleted_at')->exists()) {
                $this->resume($id);
            }
        });
    }

    public function resume(int $id): void
    {
        $m = DB::table('monitoring_monitors')->where('id', $id)->first();
        $s = DB::table('monitoring_states')->where('monitor_id', $id)->first();
        $at = MonitoringTime::now();
        $c = json_decode($m->config, true);
        $gen = $s->generation + 1;
        $period = DB::table('monitoring_periods')->insertGetId(['monitor_id' => $id, 'generation' => $gen, 'started_at' => MonitoringTime::store($at)]);
        $deadline = $m->type === 'heartbeat' ? MonitoringTime::store($at->addSeconds($c['expected_interval_seconds'] + $c['grace_seconds'])) : null;
        DB::table('monitoring_states')->where('monitor_id', $id)->update(['generation' => $gen, 'period_id' => $period, 'cursor_at' => MonitoringTime::store($at), 'availability' => 'unknown', 'phase' => null,
            'deadline_at' => $deadline, 'next_due_at' => $deadline ?? self::phase($id, app(MonitoringSettings::class)->effective($m)['interval'])]);
    }

    public function siteChanged(Site $site, ?bool $enabled = null, bool $urlChanged = false): void
    {
        $m = $this->primary($site, $enabled ?? true);
        if (! $m) {
            return;
        }
        $intentChanged = $enabled !== null && (bool) $m->enabled !== $enabled;
        if ($intentChanged) {
            $this->reset($m->id, $enabled ? 'resumed' : 'paused', false);
            DB::table('monitoring_monitors')->where('id', $m->id)->update(['enabled' => $enabled]);
            if ($enabled) {
                $this->resume($m->id);
            }
        } elseif ($urlChanged && empty(json_decode($m->config, true)['url'])) {
            $this->reset($m->id, 'target_changed');
        }
        if ($urlChanged) {
            foreach (DB::table('monitoring_monitors')->where('site_id', $site->id)->where('type', 'http')->whereNull('slot')->get() as $additional) {
                if (empty(json_decode($additional->config, true)['url'])) {
                    $this->reset($additional->id, 'target_changed');
                }
            }
        }
    }

    public function siteDeleted(Site $site): void
    {
        if (! Schema::hasTable('monitoring_monitors')) {
            return;
        }
        foreach (DB::table('monitoring_monitors')->where('site_id', $site->id)->pluck('id') as $id) {
            $this->reset($id, 'deleted', false);
        }
    }

    public function siteRestored(Site $site): void
    {
        $this->primary($site, false);
        foreach (DB::table('monitoring_monitors')->where('site_id', $site->id)->where('enabled', true)->pluck('id') as $id) {
            $this->reset($id, 'resumed');
        }
    }
}
