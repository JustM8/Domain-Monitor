<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Site\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SiteMonitor
{
    public function __construct(
        private SiteProbe $probe,
        private MonitoringNotifications $notifications,
        private MonitoringHistory $history,
    ) {}

    public function check(Site $site, bool $manual = false): array
    {
        $lock = Cache::lock('monitoring:site:'.$site->id, 120);
        if (! $lock->get()) {
            throw new CheckAlreadyRunning('Перевірка цього сайту вже виконується.');
        }
        try {
            $site = $site->fresh();
            if (! $site || (! $manual && ! $site->monitoring_enabled)) {
                throw new CheckAlreadyRunning('Автоматичний моніторинг призупинено.');
            }
            $started = CarbonImmutable::now('UTC')->startOfSecond();
            $fingerprint = $site->only(['url', 'monitoring_url', 'monitoring_content', 'monitoring_status_codes',
                'monitoring_interval', 'monitoring_timeout', 'monitoring_failure_threshold', 'monitoring_enabled', 'monitoring_revision']);
            $result = $this->probe->run($site);

            return DB::transaction(function () use ($site, $fingerprint, $result, $manual, $started) {
                $site = Site::query()->lockForUpdate()->find($site->id);
                if (! $site || $site->only(array_keys($fingerprint)) !== $fingerprint) {
                    throw new CheckAlreadyRunning('Параметри сайту змінилися. Повторіть перевірку.');
                }
                if (! $site->is_active && $site->confirmed_state === 'disabled'
                    && $site->confirmed_control_version === $site->control_version) {
                    $result['availability'] = 'planned';
                }
                $at = CarbonImmutable::now('UTC')->startOfSecond();
                DB::table('monitoring_checks')->insert($result + ['site_id' => $site->id, 'manual' => $manual, 'checked_at' => $at]);
                // Diagnostics do not move the scheduled check or affect automatic incidents/statistics.
                if ($manual) {
                    return $result;
                }
                $this->history->start($site);
                DB::table('monitoring_states')->insertOrIgnore(['site_id' => $site->id, 'created_at' => $at, 'updated_at' => $at]);
                $state = DB::table('monitoring_states')->where('site_id', $site->id)->lockForUpdate()->first();
                $this->history->flush($site, $state, $at);
                $freshSequence = $state->last_checked_at && $state->observed_interval
                    && CarbonImmutable::parse($state->last_checked_at, 'UTC')->addMinutes($state->observed_interval * 2)->gte($at);
                $down = $result['availability'] === 'down';
                $failures = $down ? ($freshSequence ? $state->consecutive_failures : 0) + 1 : 0;
                $firstFailed = $down ? ($freshSequence && $state->first_failed_at ? $state->first_failed_at : $at) : null;
                // Anchor to the cron minute, not response completion, to avoid skipping the next slot.
                $delay = $down && $failures < $site->monitoring_failure_threshold ? 1 : $site->monitoring_interval;
                DB::table('monitoring_states')->where('site_id', $site->id)->update([
                    'availability' => $result['availability'], 'technical_availability' => $result['technical_availability'],
                    'http_status' => $result['http_status'], 'response_ms' => $result['response_ms'], 'error' => $result['error'],
                    'checked_url' => $result['checked_url'], 'consecutive_failures' => $failures,
                    'first_failed_at' => $firstFailed, 'last_checked_at' => $at, 'coverage_cursor' => $at,
                    'observed_interval' => $site->monitoring_interval,
                    'next_check_at' => $started->startOfMinute()->addMinutes($delay), 'updated_at' => $at,
                ]);
                $key = ['site_id' => $site->id, 'day' => $at->timezone(config('monitoring.timezone'))->toDateString()];
                DB::table('monitoring_daily')->insertOrIgnore($key);
                DB::table('monitoring_daily')->where($key)->update([
                    'checks' => DB::raw('checks + 1'),
                    'successful' => DB::raw('successful + '.($result['availability'] === 'up' ? 1 : 0)),
                    'planned' => DB::raw('planned + '.($result['availability'] === 'planned' ? 1 : 0)),
                ]);
                // Latency describes successful HTTP checks, excluding planned maintenance/errors.
                if ($result['availability'] === 'up') {
                    $this->history->sample($site, $result['response_ms']);
                }
                $incident = DB::table('monitoring_incidents')->where('site_id', $site->id)->whereNull('closed_at')->first();
                if ($down && $failures >= $site->monitoring_failure_threshold && ! $incident) {
                    $id = DB::table('monitoring_incidents')->insertGetId([
                        'site_id' => $site->id, 'opened_at' => $firstFailed, 'detected_at' => $at, 'error' => $result['error'],
                    ]);
                    $this->notifications->enqueue($id, 'down');
                } elseif ($incident && ! $down) {
                    DB::table('monitoring_incidents')->where('id', $incident->id)->update([
                        'closed_at' => $at, 'close_reason' => $result['availability'],
                    ]);
                    $this->notifications->enqueue($incident->id, $result['availability'] === 'up' ? 'recovered' : 'planned');
                }

                return $result;
            });
        } finally {
            $lock->release();
        }
    }
}
