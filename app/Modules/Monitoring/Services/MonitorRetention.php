<?php

namespace App\Modules\Monitoring\Services;

use Illuminate\Support\Facades\DB;

final class MonitorRetention
{
    public function prune(int $limit = 100): int
    {
        MonitoringTime::session();
        $settings = app(MonitoringSettings::class)->get();
        $at = MonitoringTime::now();
        // Oldest cursor first: bounded passes remain fair without starving later IDs.
        $ids = DB::table('monitoring_states')->orderBy('cursor_at')->orderBy('monitor_id')->limit($limit)->pluck('monitor_id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $at, $settings) {
                $m = DB::table('monitoring_monitors')->where('id', $id)->lockForUpdate()->first();
                $s = DB::table('monitoring_states')->where('monitor_id', $id)->lockForUpdate()->first();
                app(MonitorHistory::class)->flush($m, $s, $at);
                DB::table('monitoring_diagnostics')->where('monitor_id', $id)->where('expires_at', '<=', MonitoringTime::store($at))->delete();
                $today = $at->setTimezone(MonitoringTime::CALENDAR)->startOfDay();
                DB::table('monitoring_rollups')->where('monitor_id', $id)->where('grain', 'day')->where('bucket_key', '<', $today->toDateString())->update(['finalized' => true]);
                $cut = $today->subDays($settings->span_days)->utc();
                $spans = DB::table('monitoring_spans')->where('monitor_id', $id);
                if ((clone $spans)->count() > 10000) {
                    $pressure = (clone $spans)->orderByDesc('ended_at')->skip(9999)->first();
                    $candidate = MonitoringTime::parse($pressure->ended_at)->setTimezone(MonitoringTime::CALENDAR)->startOfDay()->utc();
                    $cut = $cut->max($candidate)->min($today->utc());
                }
                // Aggregate equality verifies the authoritative day summary before detail deletion.
                $valid = ! DB::table('monitoring_rollups')->where('monitor_id', $id)->where('grain', 'day')->where('bucket_key', '<', $cut->setTimezone(MonitoringTime::CALENDAR)->toDateString())
                    ->whereRaw('expected_us != up_us + down_us + unknown_us + planned_us')->exists();
                if ($valid) {
                    (clone $spans)->where('ended_at', '<=', MonitoringTime::store($cut))->delete();
                    (clone $spans)->where('started_at', '<', MonitoringTime::store($cut))->where('ended_at', '>', MonitoringTime::store($cut))->update(['started_at' => MonitoringTime::store($cut)]);
                    DB::table('monitoring_periods')->where('monitor_id', $id)->where('ended_at', '<=', MonitoringTime::store($cut))->delete();
                    DB::table('monitoring_periods')->where('monitor_id', $id)->where('started_at', '<', MonitoringTime::store($cut))->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', MonitoringTime::store($cut)))->update(['started_at' => MonitoringTime::store($cut)]);
                    DB::table('monitoring_states')->where('monitor_id', $id)->update(['detail_available_from' => MonitoringTime::store($cut->max(MonitoringTime::parse($s->detail_available_from)))]);
                }
                $monthlyCut = $today->subYears($settings->daily_years)->startOfMonth()->toDateString();
                $months = DB::table('monitoring_rollups')->where('monitor_id', $id)->where('grain', 'day')->where('bucket_key', '<', $monthlyCut)->get()->groupBy(fn ($r) => substr($r->bucket_key, 0, 7));
                foreach ($months as $month => $rows) {
                    if ($rows->contains(fn ($r) => ! $r->finalized)) {
                        continue;
                    }
                    $v = ['monitor_id' => $id, 'grain' => 'month', 'bucket_key' => $month, 'finalized' => true];
                    $hist = [];
                    foreach (['expected_us', 'up_us', 'down_us', 'unknown_us', 'planned_us', 'sample_count', 'latency_sum'] as $k) {
                        $v[$k] = $rows->sum($k);
                    }
                    $v['latency_min'] = $rows->whereNotNull('latency_min')->min('latency_min');
                    $v['latency_max'] = $rows->whereNotNull('latency_max')->max('latency_max');
                    foreach ($rows as $r) {
                        foreach (json_decode($r->histogram ?? '[]', true) as $k => $n) {
                            $hist[$k] = ($hist[$k] ?? 0) + $n;
                        }
                    }
                    $v['histogram'] = json_encode($hist);
                    DB::table('monitoring_rollups')->updateOrInsert(['monitor_id' => $id, 'grain' => 'month', 'bucket_key' => $month], $v);
                    DB::table('monitoring_rollups')->whereIn('id', $rows->pluck('id'))->delete(); // Same transaction: no partial-month reaggregation.
                }
                DB::table('monitoring_heartbeat_credentials')->where('monitor_id', $id)->where(fn ($q) => $q->where('expires_at', '<=', MonitoringTime::store($at))->orWhereNotNull('revoked_at'))->delete();
            });
        }
        DB::table('monitoring_runs')->where('status', 'completed')->where('started_at', '<', MonitoringTime::store($at->subDays($settings->run_days)))->delete();
        DB::table('monitoring_runs')->whereIn('status', ['failed', 'interrupted'])->where('started_at', '<', MonitoringTime::store($at->subDays($settings->failed_run_days)))->delete();
        DB::table('monitoring_deliveries')->whereNotNull('terminal_at')->where('terminal_at', '<', MonitoringTime::store($at->subDays($settings->delivery_days)))->delete();
        DB::table('monitoring_events')->where('occurred_at', '<', MonitoringTime::store($at->subDays($settings->event_days)))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('monitoring_deliveries')->whereColumn('event_id', 'monitoring_events.id'))->delete();

        return count($ids);
    }
}
