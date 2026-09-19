<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Services\MonitoringNotifications;
use App\Modules\Monitoring\Services\SiteMonitor;
use App\Modules\Site\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RunMonitoring extends Command
{
    protected $signature = 'monitoring:run';

    protected $description = 'Check enabled sites in a bounded, non-overlapping batch';

    public function handle(SiteMonitor $monitor, MonitoringNotifications $notifications): int
    {
        $seconds = max(30, min(900, (int) config('monitoring.max_seconds')));
        $lock = Cache::lock('monitoring:run', $seconds + 180);
        if (! $lock->get()) {
            $this->info('Попередній прогон ще працює.');

            return self::SUCCESS;
        }
        $run = null;
        try {
            // Owning the lock means earlier unfinished runs have lost their lease.
            DB::table('monitoring_runs')->where('status', 'running')->update(['status' => 'interrupted', 'finished_at' => now()]);
            app(\App\Modules\TelegramSupport\Services\SupportWebhookInbox::class)->recoverInterrupted();
            DB::table('site_control_attempts')->where('status', 'pending')->where('started_at', '<', now()->subMinutes(15))
                ->update(['status' => 'interrupted', 'finished_at' => now(), 'message' => 'Процес перервано. Повторіть синхронізацію.']);
            $run = DB::table('monitoring_runs')->insertGetId(['started_at' => now()]);
            $deadline = microtime(true) + $seconds;
            $notifications->deliver(1, $deadline);
            $sites = Site::query()->where('monitoring_enabled', true)
                ->leftJoin('monitoring_states as ms', 'ms.site_id', '=', 'sites.id')
                ->where(fn ($q) => $q->whereNull('ms.next_check_at')->orWhere('ms.next_check_at', '<=', now()))
                ->orderByRaw('CASE WHEN ms.next_check_at IS NULL THEN 0 ELSE 1 END')->orderBy('ms.next_check_at')->orderBy('sites.id')
                ->select('sites.*')->limit(max(1, min(500, (int) config('monitoring.batch_size'))))->get();
            $checked = 0;
            foreach ($sites as $site) {
                if (microtime(true) >= $deadline - ($site->monitoring_timeout * 4 + 5)) {
                    break;
                }
                try {
                    $monitor->check($site);
                    $checked++;
                    $notifications->deliver(1, $deadline);
                } catch (\App\Modules\Monitoring\Services\CheckAlreadyRunning $e) {
                    continue;
                }
                DB::table('monitoring_runs')->where('id', $run)->update(['checked' => $checked]);
                usleep(max(0, min(3000, (int) config('monitoring.pause_ms'))) * 1000);
            }
            $notifications->deliver(2, $deadline);
            DB::table('monitoring_checks')->where('checked_at', '<', now()->subDays(config('monitoring.detail_days')))->delete();
            DB::table('monitoring_daily')->where('day', '<', now()->subDays(config('monitoring.daily_days'))->toDateString())->delete();
            DB::table('monitoring_runs')->where('started_at', '<', now()->subDays(30))->delete();
            DB::table('monitoring_runs')->where('id', $run)->update(['status' => 'completed', 'finished_at' => now()]);
            $this->info('Перевірено сайтів: '.$checked);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($run) {
                DB::table('monitoring_runs')->where('id', $run)->update(['status' => 'failed', 'finished_at' => now(), 'error' => $e::class]);
            }
            $this->error('Прогон не завершено. Перевірте вкладку «Моніторинг» і конфігурацію.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
