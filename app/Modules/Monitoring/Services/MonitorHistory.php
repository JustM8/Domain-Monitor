<?php

namespace App\Modules\Monitoring\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MonitorHistory
{
    public const BUCKETS = [100, 250, 500, 1000, 2000, 5000, 10000, 30000, 120000];

    public function segments(object $s, CarbonImmutable $at): array
    {
        if (! $s->period_id || ! $s->cursor_at) {
            return [];
        }
        $from = MonitoringTime::parse($s->cursor_at);
        if ($at->lte($from)) {
            return [];
        }
        $valid = $s->valid_until ? MonitoringTime::parse($s->valid_until) : $from;
        $cut = $valid->min($at)->max($from);
        $parts = [];
        if ($cut->gt($from)) {
            $parts[] = [$from, $cut, $s->availability];
        }
        if ($at->gt($cut)) {
            $parts[] = [$cut, $at, 'unknown'];
        }

        return $parts;
    }

    public function flush(object $m, object $s, CarbonImmutable $at): void
    {
        foreach ($this->segments($s, $at) as [$from, $to, $state]) {
            $last = DB::table('monitoring_spans')->where('monitor_id', $m->id)->latest('id')->first();
            if ($last && $last->generation === $s->generation && $last->availability === $state && MonitoringTime::parse($last->ended_at)->eq($from)) {
                DB::table('monitoring_spans')->where('id', $last->id)->update(['ended_at' => MonitoringTime::store($to)]);
            } else {
                DB::table('monitoring_spans')->insert(['monitor_id' => $m->id, 'generation' => $s->generation, 'availability' => $state, 'started_at' => MonitoringTime::store($from), 'ended_at' => MonitoringTime::store($to)]);
            }
            $this->split($from, $to, function ($day, $us) use ($m, $state) {
                $r = $this->day($m->id, $day);
                DB::table('monitoring_rollups')->where('id', $r->id)->update(['expected_us' => $r->expected_us + $us, $state.'_us' => $r->{$state.'_us'} + $us, 'finalized' => false]);
            });
        }
        $s->cursor_at = MonitoringTime::store($at);
        DB::table('monitoring_states')->where('monitor_id', $m->id)->update(['cursor_at' => $s->cursor_at]);
    }

    public function split(CarbonImmutable $from, CarbonImmutable $to, callable $apply): void
    {
        while ($from->lt($to)) {
            $local = $from->setTimezone(MonitoringTime::CALENDAR);
            $end = $local->startOfDay()->addDay()->utc()->min($to);
            $apply($local->toDateString(), (int) $from->diffInMicroseconds($end));
            $from = $end;
        }
    }

    public function day(int $id, string $day): object
    {
        DB::table('monitoring_rollups')->insertOrIgnore(['monitor_id' => $id, 'grain' => 'day', 'bucket_key' => $day]);

        return DB::table('monitoring_rollups')->where('monitor_id', $id)->where('grain', 'day')->where('bucket_key', $day)->first();
    }

    public function sample(int $id, int $ms, CarbonImmutable $at): void
    {
        $r = $this->day($id, $at->setTimezone(MonitoringTime::CALENDAR)->toDateString());
        $h = json_decode($r->histogram ?? '[]', true);
        $key = 'overflow';
        foreach (self::BUCKETS as $bound) {
            if ($ms <= $bound) {
                $key = (string) $bound;
                break;
            }
        }
        $h[$key] = ($h[$key] ?? 0) + 1;
        DB::table('monitoring_rollups')->where('id', $r->id)->update(['sample_count' => $r->sample_count + 1, 'latency_sum' => $r->latency_sum + $ms,
            'latency_min' => $r->latency_min === null ? $ms : min($r->latency_min, $ms), 'latency_max' => max($r->latency_max ?? 0, $ms), 'histogram' => json_encode($h)]);
    }

    public function report(int $id, int $days = 30): array
    {
        $today = MonitoringTime::now()->setTimezone(MonitoringTime::CALENDAR)->startOfDay();
        $from = $today->subDays($days - 1)->toDateString();
        $rows = DB::table('monitoring_rollups')->where('monitor_id', $id)->where('grain', 'day')->where('bucket_key', '>=', $from)->orderBy('bucket_key')->get()->keyBy('bucket_key')->map(fn ($r) => (array) $r)->all();
        $s = DB::table('monitoring_states')->where('monitor_id', $id)->first();
        foreach ($this->segments($s, MonitoringTime::now()) as [$a, $b, $state]) {
            $this->split($a, $b, function ($day, $us) use (&$rows, $from, $state) {
                if ($day < $from) {
                    return;
                }
                $rows[$day] ??= ['bucket_key' => $day, 'expected_us' => 0, 'up_us' => 0, 'down_us' => 0, 'unknown_us' => 0, 'planned_us' => 0, 'sample_count' => 0, 'latency_sum' => 0, 'histogram' => null];
                $rows[$day]['expected_us'] += $us;
                $rows[$day][$state.'_us'] += $us;
            });
        }
        ksort($rows);

        return array_values(array_map(fn ($r) => $r + ['uptime' => $r['up_us'] + $r['down_us'] ? 100 * $r['up_us'] / ($r['up_us'] + $r['down_us']) : null,
            'coverage' => $r['expected_us'] > $r['planned_us'] ? 100 * ($r['up_us'] + $r['down_us']) / ($r['expected_us'] - $r['planned_us']) : null], $rows));
    }

    public static function availability(object $s): string
    {
        return $s->valid_until && MonitoringTime::parse($s->valid_until)->gt(MonitoringTime::now()) ? $s->availability : 'unknown';
    }

    public static function summary(array $rows): array
    {
        $total = array_fill_keys(['expected_us', 'up_us', 'down_us', 'unknown_us', 'planned_us', 'sample_count', 'latency_sum'], 0);
        $hist = [];
        foreach ($rows as $r) {
            foreach ($total as $key => $value) {
                $total[$key] += $r[$key];
            }
            foreach (json_decode($r['histogram'] ?? '[]', true) as $key => $n) {
                $hist[$key] = ($hist[$key] ?? 0) + $n;
            }
        }
        $uptimeDenominator = $total['up_us'] + $total['down_us'];
        $coverageDenominator = $total['expected_us'] - $total['planned_us'];
        $p95 = null;
        $count = 0;
        foreach (array_merge(self::BUCKETS, ['overflow']) as $bucket) {
            $count += $hist[$bucket] ?? 0;
            if ($total['sample_count'] && $count >= ceil($total['sample_count'] * .95)) {
                $p95 = $bucket;
                break;
            }
        }

        return $total + ['uptime' => $uptimeDenominator ? 100 * $total['up_us'] / $uptimeDenominator : null, 'coverage' => $coverageDenominator ? 100 * $uptimeDenominator / $coverageDenominator : null, 'p95' => $p95];
    }

    // Caller holds Monitor→State locks. Quotas never interrupt transitions.
    public function diagnostic(object $m, object $s, string $kind, array $evidence, CarbonImmutable $at, bool $selected = true): bool
    {
        if (! $selected) {
            return false;
        }
        $day = $at->setTimezone(MonitoringTime::CALENDAR)->toDateString();
        $q = DB::table('monitoring_diagnostics')->where('monitor_id', $m->id);
        $daily = (clone $q)->where('day', $day);
        $cap = $kind === 'manual' ? 20 : 8;
        if ((clone $q)->count() >= 448 || (clone $daily)->count() >= 32 || (clone $daily)->where('kind', $kind)->count() >= $cap) {
            DB::table('monitoring_states')->where('monitor_id', $m->id)->increment('diagnostics_suppressed');

            return false;
        }
        $settings = app(MonitoringSettings::class)->get();
        $days = $kind === 'manual' ? $settings->manual_diagnostic_days : $settings->failed_diagnostic_days;
        DB::table('monitoring_diagnostics')->insert(['monitor_id' => $m->id, 'kind' => $kind, 'day' => $day, 'recorded_at' => MonitoringTime::store($at), 'expires_at' => MonitoringTime::store($at->addDays($days)), 'evidence' => json_encode($evidence)]);

        return true;
    }
}
