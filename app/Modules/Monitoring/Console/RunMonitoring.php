<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Services\MonitorEvents;
use App\Modules\Monitoring\Services\MonitorHeartbeat;
use App\Modules\Monitoring\Services\MonitoringTime;
use App\Modules\Monitoring\Services\MonitorRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunMonitoring extends Command
{
    protected $signature = 'monitoring:run';

    protected $description = 'Bounded V2 probes and heartbeat evaluation; preserves ancillary portal recovery';

    public function handle(MonitorRunner $runner, MonitorHeartbeat $heartbeat, MonitorEvents $events): int
    {
        if (! Schema::hasTable('monitoring_v2_install') || DB::table('monitoring_v2_install')->value('stage') !== 'complete') {
            $this->error('Monitoring V2 cutover is not complete.');

            return self::FAILURE;
        }
        MonitoringTime::session();
        $seconds = max(20, min(240, (int) config('monitoring.max_seconds')));
        $lock = Cache::lock('monitoring:run', $seconds + 180);
        if (! $lock->get()) {
            return self::SUCCESS;
        } $run = null;
        try {
            $at = MonitoringTime::now();
            DB::table('monitoring_runs')->where('status', 'running')->where('started_at', '<', MonitoringTime::store($at->subSeconds($seconds + 180)))->update(['status' => 'interrupted', 'finished_at' => MonitoringTime::store($at)]);
            // Preserve the ancillary contract outside V2 services.
            app(\App\Modules\TelegramSupport\Services\SupportWebhookInbox::class)->recoverInterrupted();
            DB::table('site_control_attempts')->where('status', 'pending')->where('started_at', '<', now()->subMinutes(15))->update(['status' => 'interrupted', 'finished_at' => now(), 'message' => 'Процес перервано. Повторіть синхронізацію.']);
            $run = DB::table('monitoring_runs')->insertGetId(['started_at' => MonitoringTime::store($at)]);
            $deadline = microtime(true) + $seconds;
            $heartbeat->evaluate(max(1, min(500, (int) config('monitoring.batch_size'))));
            $n = $runner->run(max(1, min(500, (int) config('monitoring.batch_size'))), $deadline);
            $events->deliver(2, $deadline);
            DB::table('monitoring_runs')->where('id', $run)->update(['checked' => $n, 'status' => 'completed', 'finished_at' => MonitoringTime::store(MonitoringTime::now())]);
            $this->info('Monitoring V2: '.$n);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($run) {
                DB::table('monitoring_runs')->where('id', $run)->update(['status' => 'failed', 'error' => $e::class, 'finished_at' => MonitoringTime::store(MonitoringTime::now())]);
            }
            $this->error('Monitoring V2 run failed. Check schema and operational summary.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
