@extends('layouts.portal')
@section('content')
<h1>{{ $monitor->site->name }} / {{ $monitor->name }}</h1>
<p>{{ strtoupper(\App\Modules\Monitoring\Services\MonitorHistory::availability($state)) }} · {{ $state->phase }} · {{ $monitor->type }} · {{ $zone }}</p>
<p>Observation: {{ \App\Modules\Monitoring\Services\MonitoringTime::display($state->last_observed_at, $zone) }} · Due/deadline: {{ \App\Modules\Monitoring\Services\MonitoringTime::display($state->next_due_at, $zone) }}</p>
@if($monitor->type !== 'heartbeat')<form method="POST" action="{{ route('portal.monitoring.check', $monitor) }}">@csrf<button class="btn btn-outline-primary">Manual diagnostic</button></form>@endif
@can('monitoring.write')
<details class="card card-body my-3"><summary>Monitor config</summary><form method="POST" action="{{ route('portal.monitoring.update', $monitor) }}">@csrf @method('PUT') @include('portal.monitoring.fields')<button class="btn btn-primary mt-3">Зберегти</button></form></details>
<form method="POST" action="{{ route('portal.monitoring.timezone', $monitor) }}" class="my-3">@csrf @method('PUT')<label>Site display timezone (empty = inherit)<input name="display_timezone" class="form-control" placeholder="Europe/Kyiv"></label><button class="btn btn-outline-primary">Зберегти timezone</button></form>
@if($monitor->type === 'heartbeat')
<div class="card card-body my-3"><p>Completion-only HTTPS POST {{ url('/api/monitoring/heartbeat') }}. Header: Authorization: Bearer PUBLIC_ID.SECRET. Optional JSON: job_run_id. Secret показується один раз.</p>
@if(session('heartbeatCredential'))<pre class="text-wrap">{{ session('heartbeatCredential') }}</pre>@endif
<form method="POST" action="{{ route('portal.monitoring.credential', $monitor) }}">@csrf<label>Overlap sec (0–3600)<input class="form-control" type="number" name="overlap_seconds" value="0" min="0" max="3600"></label><button class="btn btn-primary">Rotate / issue</button><button class="btn btn-outline-danger" name="revoke" value="1">Revoke all</button></form></div>
@endif
@endcan
@if($certificate)<p>SSL expires {{ \App\Modules\Monitoring\Services\MonitoringTime::display($certificate->not_after, $zone) }} · verified {{ \App\Modules\Monitoring\Services\MonitoringTime::display($certificate->verified_at, $zone) }} · {{ $certificate->last_error }}</p>@endif
<h2>Recent incidents</h2><table class="table"><thead><tr><th>Start / detected</th><th>Close</th><th>Cause</th></tr></thead><tbody>@foreach($incidents as $i)<tr><td>{{ \App\Modules\Monitoring\Services\MonitoringTime::display($i->started_at, $zone) }} / {{ \App\Modules\Monitoring\Services\MonitoringTime::display($i->detected_at, $zone) }}</td><td>{{ \App\Modules\Monitoring\Services\MonitoringTime::display($i->closed_at, $zone) }} {{ $i->close_reason }}</td><td>{{ $i->cause }}</td></tr>@endforeach</tbody></table>
<h2>7 / 30 days · Calendar Europe/Kyiv</h2><p>Uptime = UP/(UP+DOWN). Coverage окремо. До detail watermark {{ \App\Modules\Monitoring\Services\MonitoringTime::display($state->detail_available_from) }} доступні лише агрегати.</p>
@foreach([7,30] as $days)
@php($summary = \App\Modules\Monitoring\Services\MonitorHistory::summary(array_values(array_filter($rollups, fn($r) => $r['bucket_key'] >= \App\Modules\Monitoring\Services\MonitoringTime::now()->setTimezone('Europe/Kyiv')->subDays($days-1)->toDateString()))))
<p>{{ $days }} days: uptime {{ $summary['uptime'] === null ? '—' : round($summary['uptime'],2).'%' }} · coverage {{ $summary['coverage'] === null ? '—' : round($summary['coverage'],2).'%' }} · p95 upper bucket {{ $summary['p95'] ?? '—' }} ms</p>
@endforeach
<table class="table"><thead><tr><th>Day</th><th>UP sec</th><th>DOWN sec</th><th>UNKNOWN sec</th><th>PLANNED sec</th><th>Uptime</th><th>Coverage</th><th>Latency avg</th></tr></thead><tbody>@foreach($rollups as $r)<tr><td>{{ $r['bucket_key'] }}</td>@foreach(['up_us','down_us','unknown_us','planned_us'] as $k)<td>{{ round($r[$k]/1000000) }}</td>@endforeach<td>{{ $r['uptime'] === null ? '—' : round($r['uptime'],2).'%' }}</td><td>{{ $r['coverage'] === null ? '—' : round($r['coverage'],2).'%' }}</td><td>{{ $r['sample_count'] ? round($r['latency_sum']/$r['sample_count']).' ms' : '—' }}</td></tr>@endforeach</tbody></table>
<h2>Selected diagnostics</h2><p>Suppressed: {{ $state->diagnostics_suppressed }}</p><table class="table"><thead><tr><th>Time</th><th>Kind</th><th>Evidence</th></tr></thead><tbody>@foreach($diagnostics as $d)<tr><td>{{ \App\Modules\Monitoring\Services\MonitoringTime::display($d->recorded_at, $zone) }}</td><td>{{ $d->kind }}</td><td>{{ $d->evidence }}</td></tr>@endforeach</tbody></table>
@endsection
