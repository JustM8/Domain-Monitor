<?php

namespace App\Modules\Monitoring\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringReport
{
    public function range(Request $request, array $siteIds): array
    {
        $input = $request->validate([
            'period' => ['nullable', 'in:today,7d,30d,month,all,custom'],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', 'in:day,week,month'],
        ]);
        $zone = config('monitoring.timezone');
        $now = CarbonImmutable::now($zone);
        $period = $input['period'] ?? '30d';
        $first = DB::table('monitoring_periods')->whereIn('site_id', $siteIds)->min('started_at');
        $from = match ($period) {
            'today' => $now->startOfDay(), '7d' => $now->startOfDay()->subDays(6),
            'month' => $now->startOfMonth(),
            'all' => $first ? CarbonImmutable::parse($first, 'UTC')->timezone($zone)->startOfDay() : $now->startOfDay(),
            'custom' => CarbonImmutable::createFromFormat('!Y-m-d', $input['from'], $zone),
            default => $now->startOfDay()->subDays(29),
        };
        $to = $period === 'custom'
            ? CarbonImmutable::createFromFormat('!Y-m-d', $input['to'], $zone)->addDay()->min($now)
            : $now;
        if ($from->gt($to)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from' => 'Початок періоду має бути не пізніше поточного часу.']);
        }
        $group = $input['group'] ?? 'day';
        if ($from->diffInDays($to) > 400 && $group === 'day') {
            $group = 'month';
        }

        return compact('period', 'from', 'to', 'group', 'first');
    }

    public function build(array $siteIds, array $range): array
    {
        $from = $range['from']->utc();
        $to = $range['to']->utc();
        $buckets = [];
        $cursor = $range['from']->startOfDay();
        while ($cursor->lt($range['to'])) {
            $key = $this->key($cursor, $range['group']);
            $buckets[$key] ??= $this->emptyBucket($key);
            $cursor = $cursor->addDay();
        }
        $periods = DB::table('monitoring_periods')->whereIn('site_id', $siteIds)->where('started_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $from))->get();
        foreach ($periods as $period) {
            $this->distribute($buckets, $period->started_at, $period->ended_at ?? $to, 'expected', $range);
        }
        $spans = DB::table('monitoring_spans')->whereIn('site_id', $siteIds)->where('started_at', '<', $to)
            ->where('ended_at', '>', $from)->orderBy('started_at')->get();
        // Include the bounded tail since the last cron run, without mutating history on reads.
        $active = $periods->whereNull('ended_at')->keyBy('site_id');
        $states = DB::table('monitoring_states')->whereIn('site_id', $active->keys())->whereNotNull('coverage_cursor')->get();
        foreach ($states as $state) {
            $end = CarbonImmutable::parse($state->last_checked_at, 'UTC')->addMinutes($state->observed_interval * 2)->min($to);
            if ($end->gt(CarbonImmutable::parse($state->coverage_cursor, 'UTC'))) {
                $spans->push((object) [
                    'site_id' => $state->site_id, 'started_at' => $state->coverage_cursor,
                    'ended_at' => $end, 'availability' => $state->availability, 'checked_url' => $state->checked_url,
                ]);
            }
        }
        $longest = 0;
        $downEnds = [];
        foreach ($spans->sortBy('started_at') as $span) {
            if (! in_array($span->availability, ['up', 'down', 'planned'], true)) {
                continue;
            }
            $this->distribute($buckets, $span->started_at, $span->ended_at, $span->availability, $range);
            if ($span->availability === 'down') {
                $start = CarbonImmutable::parse($span->started_at, 'UTC')->max($from);
                $end = CarbonImmutable::parse($span->ended_at, 'UTC')->min($to);
                $previous = $downEnds[$span->site_id] ?? null;
                $length = max(0, $end->timestamp - $start->timestamp);
                if ($previous && $previous['end'] === $start->timestamp) {
                    $length += $previous['length'];
                }
                $downEnds[$span->site_id] = ['end' => $end->timestamp, 'length' => $length];
                $longest = max($longest, $length);
            }
        }
        $metrics = DB::table('monitoring_metrics')->whereIn('site_id', $siteIds)
            ->whereBetween('day', [$range['from']->toDateString(), $range['to']->subSecond()->toDateString()])->get();
        foreach ($metrics as $metric) {
            $key = $this->key(CarbonImmutable::parse($metric->day, config('monitoring.timezone')), $range['group']);
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['samples'] += $metric->samples;
            $buckets[$key]['response_sum'] += $metric->response_sum;
            $buckets[$key]['response_max'] = max($buckets[$key]['response_max'], $metric->response_max);
            foreach (json_decode($metric->histogram ?? '{}', true) ?: [] as $bound => $count) {
                $buckets[$key]['histogram'][$bound] = ($buckets[$key]['histogram'][$bound] ?? 0) + $count;
            }
        }
        $total = $this->emptyBucket('Всього');
        foreach ($buckets as $bucket) {
            foreach (['expected', 'up', 'down', 'planned', 'samples', 'response_sum'] as $field) {
                $total[$field] += $bucket[$field];
            }
            $total['response_max'] = max($total['response_max'], $bucket['response_max']);
            foreach ($bucket['histogram'] as $bound => $count) {
                $total['histogram'][$bound] = ($total['histogram'][$bound] ?? 0) + $count;
            }
        }
        $incidents = DB::table('monitoring_incidents')->whereIn('site_id', $siteIds)->where('opened_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>', $from))->count();

        return [
            'total' => $this->finish($total), 'buckets' => array_values(array_map($this->finish(...), $buckets)),
            'incidents' => $incidents, 'longest' => $longest, 'range' => $range,
        ];
    }

    private function emptyBucket(string $label): array
    {
        return ['label' => $label, 'expected' => 0, 'up' => 0, 'down' => 0, 'planned' => 0,
            'samples' => 0, 'response_sum' => 0, 'response_max' => 0, 'histogram' => []];
    }

    private function key(CarbonImmutable $date, string $group): string
    {
        return match ($group) {
            'week' => $date->startOfWeek()->toDateString(), 'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function distribute(array &$buckets, mixed $start, mixed $end, string $field, array $range): void
    {
        $start = CarbonImmutable::parse($start, 'UTC')->max($range['from'])->timezone(config('monitoring.timezone'));
        $end = CarbonImmutable::parse($end, 'UTC')->min($range['to'])->timezone(config('monitoring.timezone'));
        while ($start->lt($end)) {
            $boundary = $start->addDay()->startOfDay()->min($end);
            $key = $this->key($start, $range['group']);
            if (isset($buckets[$key])) {
                $buckets[$key][$field] += $boundary->timestamp - $start->timestamp;
            }
            $start = $boundary;
        }
    }

    private function finish(array $bucket): array
    {
        $bucket['unknown'] = max(0, $bucket['expected'] - $bucket['up'] - $bucket['down'] - $bucket['planned']);
        $observed = $bucket['up'] + $bucket['down'];
        $eligible = max(0, $bucket['expected'] - $bucket['planned']);
        $bucket['uptime'] = $observed ? round(100 * $bucket['up'] / $observed, 3) : null;
        $bucket['coverage'] = $eligible ? round(100 * $observed / $eligible, 2) : null;
        $bucket['average'] = $bucket['samples'] ? (int) round($bucket['response_sum'] / $bucket['samples']) : null;
        $bucket['p95'] = null;
        $remaining = (int) ceil($bucket['samples'] * 0.95);
        if ($remaining > 0) {
            foreach (MonitoringHistory::LATENCY_BUCKETS as $bound) {
                $remaining -= $bucket['histogram'][(string) $bound] ?? 0;
                if ($remaining <= 0) {
                    $bucket['p95'] = '≤ '.$bound.' мс';
                    break;
                }
            }
            $bucket['p95'] ??= '> 120000 мс';
        }

        return $bucket;
    }

    public static function duration(int|float $seconds): string
    {
        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return ($days ? $days.' д ' : '').($hours ? $hours.' год ' : '').($minutes ? $minutes.' хв ' : '')
            .(($seconds < 60 || $seconds % 60) ? ($seconds % 60).' с' : '');
    }
}
