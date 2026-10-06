<?php

namespace App\Modules\Monitoring\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MonitoringSettings
{
    public const DEFAULTS = ['default_check_interval_seconds' => 300, 'default_timeout_seconds' => 6, 'failure_threshold' => 2, 'failure_retry_seconds' => 60, 'recovery_threshold' => 1,
        'ssl_warning_days' => 14, 'ssl_critical_days' => 3, 'ssl_renewal_enabled' => 1, 'success_diagnostic_days' => 7, 'failed_diagnostic_days' => 14, 'manual_diagnostic_days' => 14,
        'span_days' => 90, 'daily_years' => 3, 'run_days' => 7, 'failed_run_days' => 30, 'event_days' => 180, 'delivery_days' => 90];

    public function get(): object
    {
        return DB::table('monitoring_settings')->where('id', 1)->first() ?? throw new \RuntimeException('Monitoring record missing');
    }

    public function effective(object $m): array
    {
        $s = $this->get();

        return ['interval' => $m->custom_interval_seconds ?? $s->default_check_interval_seconds, 'timeout' => $m->timeout_seconds ?? $s->default_timeout_seconds,
            'failures' => $m->failure_threshold ?? $s->failure_threshold, 'recoveries' => $m->recovery_threshold ?? $s->recovery_threshold, 'retry' => $s->failure_retry_seconds, 'checks_revision' => $s->checks_revision];
    }

    public function ports(): array
    {
        return json_decode($this->get()->tcp_allowed_ports ?? '[80,443]', true);
    }

    public function recipients(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach ($ids as $id) {
            $u = User::with('role')->find($id);
            if (! $u || ! $u->canPortal('monitoring.read') || ! $u->canUseAccessBot() || ! $u->telegramIsLinked()) {
                throw ValidationException::withMessages(['recipient_ids' => 'Потрібен активний Admin/PM із підключеним Access-ботом.']);
            }
        }

        return $ids;
    }

    public function update(array $input): void
    {
        $rules = [];
        foreach (self::DEFAULTS as $k => $v) {
            $rules[$k] = ['required', 'integer', 'between:1,365'];
        }
        $rules['default_check_interval_seconds'] = ['required', 'integer', 'between:60,86400'];
        $rules['default_timeout_seconds'] = ['required', 'integer', 'between:2,10'];
        $rules['failure_retry_seconds'] = ['required', 'integer', 'between:30,300'];
        foreach (['failure_threshold', 'recovery_threshold'] as $k) {
            $rules[$k] = ['required', 'integer', 'between:1,5'];
        }
        $rules['ssl_renewal_enabled'] = ['required', 'boolean'];
        foreach (['success_diagnostic_days', 'failed_diagnostic_days', 'manual_diagnostic_days'] as $k) {
            $rules[$k] = ['required', 'integer', 'between:1,14'];
        }
        $rules['span_days'] = ['required', 'integer', 'between:1,90'];
        $rules['daily_years'] = ['required', 'integer', 'between:1,3'];
        $rules['run_days'] = ['required', 'integer', 'between:1,7'];
        $rules['failed_run_days'] = ['required', 'integer', 'between:1,30'];
        $rules['event_days'] = ['required', 'integer', 'between:1,180'];
        $rules['delivery_days'] = ['required', 'integer', 'between:1,90'];
        $rules['tcp_allowed_ports'] = ['required', 'array', 'min:1', 'max:32'];
        $rules['tcp_allowed_ports.*'] = ['integer', 'distinct', 'between:1,65535'];
        $rules['recipient_ids'] = ['sometimes', 'array', 'max:100'];
        $rules['recipient_ids.*'] = ['integer', 'distinct'];
        $data = Validator::make($input, $rules)->validate();
        if ($data['ssl_critical_days'] >= $data['ssl_warning_days']) {
            throw ValidationException::withMessages(['ssl_critical_days' => 'Critical має бути менше Warning.']);
        }
        $ids = $this->recipients($data['recipient_ids'] ?? []);
        unset($data['recipient_ids']);
        DB::transaction(function () use ($data, $ids) {
            $old = DB::table('monitoring_settings')->where('id', 1)->lockForUpdate()->first();
            $checksChanged = false;
            foreach (['default_check_interval_seconds', 'default_timeout_seconds', 'failure_threshold', 'failure_retry_seconds', 'recovery_threshold', 'tcp_allowed_ports'] as $k) {
                $value = $k === 'tcp_allowed_ports' ? json_encode($data[$k]) : $data[$k];
                if ((string) $old->$k !== (string) $value) {
                    $checksChanged = true;
                }
            }
            // Flush old freshness BEFORE changing the effective defaults.
            $affected = [];
            if ($checksChanged) {
                DB::table('monitoring_monitors')->where('type', '!=', 'heartbeat')->orderBy('id')->chunkById(100, function ($rows) use ($data, $old, &$affected) {
                    foreach ($rows as $m) {
                        $changed = false;
                        foreach (['custom_interval_seconds' => 'default_check_interval_seconds', 'timeout_seconds' => 'default_timeout_seconds', 'failure_threshold' => 'failure_threshold', 'recovery_threshold' => 'recovery_threshold'] as $override => $key) {
                            if ($m->$override === null && (int) $old->$key !== (int) $data[$key]) {
                                $changed = true;
                            }
                        }
                        if ((int) $old->failure_retry_seconds !== (int) $data['failure_retry_seconds'] || ($m->type === 'tcp' && json_decode($old->tcp_allowed_ports ?? '[80,443]', true) !== $data['tcp_allowed_ports'])) {
                            $changed = true;
                        }
                        if ($changed) {
                            app(MonitorManager::class)->reset($m->id, 'settings_changed');
                            $affected[] = $m->id;
                        }
                    }
                });
            }
            $data['tcp_allowed_ports'] = json_encode($data['tcp_allowed_ports']);
            $data['checks_revision'] = $old->checks_revision + ($checksChanged ? 1 : 0);
            DB::table('monitoring_settings')->where('id', 1)->update($data);
            DB::table('monitoring_default_recipients')->delete();
            foreach ($ids as $id) {
                DB::table('monitoring_default_recipients')->insert(['user_id' => $id]);
            }
            if ($checksChanged) {
                DB::table('monitoring_monitors')->whereIn('id', $affected)->where('enabled', true)->orderBy('id')->chunkById(100, function ($rows) {
                    foreach ($rows as $m) {
                        DB::table('monitoring_states')->where('monitor_id', $m->id)->whereNotNull('period_id')->update(['next_due_at' => MonitorManager::phase($m->id, $this->effective($m)['interval'])]);
                    }
                });
            }
        });
    }
}
