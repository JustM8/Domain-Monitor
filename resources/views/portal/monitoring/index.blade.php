@extends('layouts.portal')
@section('content')
<div class="d-flex justify-content-between"><h1>Monitoring V2</h1><a href="{{ route('portal.monitoring.settings') }}">Settings</a></div>
<div class="card card-body mb-3">@foreach($totals as $k => $v)<span>{{ strtoupper($k) }}: {{ $v }}</span>@endforeach<span>Active incidents: {{ $incidents }} · SSL warnings: {{ $sslWarnings }}</span></div>
<p>Останній cron: {{ $lastRun ? \App\Modules\Monitoring\Services\MonitoringTime::display($lastRun->started_at) : '—' }} Europe/Kyiv · {{ $lastRun->status ?? '—' }}</p>
<form class="d-flex gap-2 mb-3"><input class="form-control" name="search" placeholder="Monitor name" value="{{ request('search') }}"><select class="form-select" name="type"><option value="">Усі типи</option>@foreach(['http','tcp','heartbeat'] as $t)<option @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select><button class="btn btn-primary">Фільтр</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Site / Monitor</th><th>Type</th><th>State</th><th>Last observation (Kyiv)</th><th>Next due / deadline</th><th>Latency</th><th>Incident</th></tr></thead><tbody>
@foreach($monitors as $m) @php($s = $states[$m->id])
<tr><td>{{ $m->site->name }}<br><a href="{{ route('portal.monitoring.show', $m->id) }}">{{ $m->name }}</a></td><td>{{ $m->type }}</td><td>{{ $m->enabled ? strtoupper(\App\Modules\Monitoring\Services\MonitorHistory::availability($s)) : 'PAUSED' }}<br>{{ $s->phase }}</td><td>{{ \App\Modules\Monitoring\Services\MonitoringTime::display($s->last_observed_at) }}</td><td>{{ \App\Modules\Monitoring\Services\MonitoringTime::display($s->next_due_at) }}</td><td>{{ $s->response_ms === null ? '—' : $s->response_ms.' ms' }}</td><td>{{ $s->active_incident_id ?? '—' }}</td></tr>
@endforeach
</tbody></table></div>{{ $monitors->links() }}
@can('monitoring.write')
<details class="card card-body mt-3"><summary>Додати Monitor</summary><form method="POST" action="{{ route('portal.monitoring.store') }}">@csrf
<label class="form-label">Site<select class="form-select" name="site_id">@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></label>
@include('portal.monitoring.fields', ['monitor' => null, 'selectedRecipients' => []])<button class="btn btn-primary mt-3">Створити</button></form></details>
@endcan
@endsection
