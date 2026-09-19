<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Shared\Http\SafeHttp;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SiteMonitor
{
    public function __construct(private SafeHttp $http, private MonitoringNotifications $notifications) {}

    public function check(Site $site, bool $manual = false): array
    {
        $lock = Cache::lock('monitoring:site:'.$site->id, 90);
        if (! $lock->get()) {
            throw new CheckAlreadyRunning('Перевірка цього сайту вже виконується.');
        }
        try {
            $started = microtime(true);
            $status = null;
            $error = null;
            try {
                $response = $this->http->get($site->url);
                $status = $response->status();
                $availability = $status >= 200 && $status < 300 ? 'up' : 'down';
                if ($availability === 'down') {
                    $error = 'HTTP '.$status;
                }
            } catch (\Throwable $e) {
                $availability = 'down';
                $error = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Помилка з’єднання, TLS, перенаправлення або тайм-аут.';
            }
            // Planned downtime requires confirmation from the child for this exact command version.
            $site->refresh();
            if (! $site->is_active && $site->confirmed_state === 'disabled' && $site->confirmed_control_version === $site->control_version) {
                $availability = 'planned';
            }
            $ms = (int) round((microtime(true) - $started) * 1000);

            return DB::transaction(function () use ($site, $availability, $status, $error, $ms, $manual) {
                DB::table('monitoring_states')->insertOrIgnore(['site_id' => $site->id, 'created_at' => now(), 'updated_at' => now()]);
                $state = DB::table('monitoring_states')->where('site_id', $site->id)->lockForUpdate()->first();
                $failures = $availability === 'down' ? $state->consecutive_failures + 1 : 0;
                $result = ['availability' => $availability, 'http_status' => $status, 'response_ms' => $ms, 'error' => $error];
                DB::table('monitoring_states')->where('site_id', $site->id)->update($result + [
                    'consecutive_failures' => $failures, 'last_checked_at' => now(),
                    'next_check_at' => now()->addMinutes(max(1, config('monitoring.interval_minutes'))), 'updated_at' => now(),
                ]);
                DB::table('monitoring_checks')->insert($result + ['site_id' => $site->id, 'manual' => $manual, 'checked_at' => now()]);
                $dailyKey = ['site_id' => $site->id, 'day' => now()->toDateString()];
                DB::table('monitoring_daily')->insertOrIgnore($dailyKey);
                DB::table('monitoring_daily')->where($dailyKey)->update([
                    'checks' => DB::raw('checks + 1'),
                    'successful' => DB::raw('successful + '.($availability === 'up' ? 1 : 0)),
                    'planned' => DB::raw('planned + '.($availability === 'planned' ? 1 : 0)),
                ]);
                $incident = DB::table('monitoring_incidents')->where('site_id', $site->id)->whereNull('closed_at')->first();
                if ($availability === 'down' && $failures >= 2 && ! $incident && $site->environment === 'prod') {
                    $id = DB::table('monitoring_incidents')->insertGetId(['site_id' => $site->id, 'opened_at' => now(), 'error' => $error]);
                    $this->notifications->enqueue($id, 'down');
                } elseif ($incident && $availability !== 'down') {
                    DB::table('monitoring_incidents')->where('id', $incident->id)->update(['closed_at' => now(), 'close_reason' => $availability]);
                    $this->notifications->enqueue($incident->id, $availability === 'up' ? 'recovered' : 'planned');
                }

                return $result;
            });
        } finally {
            $lock->release();
        }
    }
}
