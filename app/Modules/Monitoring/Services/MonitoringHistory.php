<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Site\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonitoringHistory
{
    public const LATENCY_BUCKETS = [100, 250, 500, 1000, 2000, 5000, 10000, 30000, 120000];

    public function start(Site $site): void
    {
        if (! DB::table('monitoring_periods')->where('site_id', $site->id)->whereNull('ended_at')->exists()) {
            DB::table('monitoring_periods')->insert(['site_id' => $site->id, 'started_at' => now()]);
        }
    }

    // Caller holds the site row lock. Persist only the bounded observation window.
    public function flush(Site $site, ?object $state, CarbonImmutable $until): void
    {
        if (! $state?->coverage_cursor || ! $state->last_checked_at || ! $state->observed_interval) {
            return;
        }
        $start = CarbonImmutable::parse($state->coverage_cursor, 'UTC');
        $end = CarbonImmutable::parse($state->last_checked_at, 'UTC')->addMinutes($state->observed_interval * 2)->min($until);
        if ($end->lte($start) || ! in_array($state->availability, ['up', 'down', 'planned'], true)) {
            return;
        }
        $last = DB::table('monitoring_spans')->where('site_id', $site->id)->orderByDesc('id')->first();
        if ($last && $last->ended_at === $start->toDateTimeString()
            && $last->availability === $state->availability && $last->checked_url === $state->checked_url) {
            DB::table('monitoring_spans')->where('id', $last->id)->update(['ended_at' => $end]);
        } else {
            DB::table('monitoring_spans')->insert([
                'site_id' => $site->id, 'started_at' => $start, 'ended_at' => $end,
                'availability' => $state->availability, 'checked_url' => $state->checked_url,
            ]);
        }
        DB::table('monitoring_states')->where('site_id', $site->id)->update(['coverage_cursor' => $until]);
    }

    public function reset(Site $site, string $reason): void
    {
        $state = DB::table('monitoring_states')->where('site_id', $site->id)->lockForUpdate()->first();
        $this->flush($site, $state, CarbonImmutable::now('UTC'));
        DB::table('monitoring_states')->where('site_id', $site->id)->update([
            'availability' => 'unknown', 'technical_availability' => null,
            'last_checked_at' => null, 'next_check_at' => null, 'coverage_cursor' => null,
            'consecutive_failures' => 0, 'first_failed_at' => null,
            'http_status' => null, 'response_ms' => null, 'error' => null,
        ]);
        // A pause or target change is not a recovery.
        $incidents = DB::table('monitoring_incidents')->where('site_id', $site->id)->whereNull('closed_at')->pluck('id');
        DB::table('monitoring_incidents')->whereIn('id', $incidents)->update(['closed_at' => now(), 'close_reason' => $reason]);
        DB::table('monitoring_notifications')->whereIn('incident_id', $incidents)->whereNull('sent_at')
            ->update(['cancelled_at' => now()]);
    }

    public function stop(Site $site, string $reason = 'paused'): void
    {
        $this->reset($site, $reason);
        DB::table('monitoring_periods')->where('site_id', $site->id)->whereNull('ended_at')->update(['ended_at' => now()]);
    }

    public function sample(Site $site, int $ms): void
    {
        $key = ['site_id' => $site->id, 'day' => now()->timezone(config('monitoring.timezone'))->toDateString()];
        DB::table('monitoring_metrics')->insertOrIgnore($key);
        $row = DB::table('monitoring_metrics')->where($key)->lockForUpdate()->first();
        $histogram = json_decode($row->histogram ?? '{}', true) ?: [];
        $bucket = 'overflow';
        foreach (self::LATENCY_BUCKETS as $bound) {
            if ($ms <= $bound) {
                $bucket = (string) $bound;
                break;
            }
        }
        $histogram[$bucket] = ($histogram[$bucket] ?? 0) + 1;
        DB::table('monitoring_metrics')->where('id', $row->id)->update([
            'samples' => $row->samples + 1, 'response_sum' => $row->response_sum + $ms,
            'response_max' => max($row->response_max, $ms), 'histogram' => json_encode($histogram),
        ]);
    }
}
