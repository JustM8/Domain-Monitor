@extends('layouts.portal')
@section('page_title', 'Моніторинг · '.$site->name)
@section('content')
@php
    $labels = ['up'=>'Доступний','down'=>'Недоступний','planned'=>'Планові роботи','unknown'=>'Немає даних'];
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date, 'UTC')->timezone(config('monitoring.timezone'))->format('d.m.Y H:i:s') : '—';
@endphp
<div class="portal-card p-4 mb-3 d-flex justify-content-between gap-3 flex-wrap">
    <div><a href="{{ route('portal.monitoring.index') }}">← Моніторинг</a><h1 class="h4 mt-2">{{ $site->name }}</h1><div class="portal-soft text-break">{{ $site->url }}</div><div class="mt-2">{{ $site->remote_control_enabled ? 'Моніторинг і керування' : 'Лише моніторинг' }} · {{ $site->monitoring_enabled ? 'Cron: кожні '.$site->monitoring_interval.' хв' : 'Автоматичні перевірки призупинено' }}</div></div>
    <div class="d-flex gap-2 align-items-center">@can('sites.read')<a class="btn btn-outline-secondary" href="{{ route('portal.sites.show', $site) }}">Картка сайту</a>@endcan<form method="POST" action="{{ route('portal.monitoring.check', $site) }}">@csrf<button class="btn btn-primary">Перевірити зараз</button></form></div>
</div>
@if($site->monitoring_enabled && (! $state?->last_checked_at || \Carbon\Carbon::parse($state->last_checked_at, 'UTC')->addMinutes(($state->observed_interval ?: $site->monitoring_interval) * 2)->lt(now())))
<div class="alert alert-warning">Немає свіжих автоматичних даних. Перевірте cron; останній збережений результат нижче може бути застарілим.</div>
@endif
@if($latestCheck)
<div class="portal-card p-3 mb-3">
    <strong>Остання {{ $latestCheck->manual ? 'ручна' : 'автоматична' }} перевірка:</strong> {{ $labels[$latestCheck->availability] ?? $latestCheck->availability }} · {{ $formatDate($latestCheck->checked_at) }} · HTTP {{ $latestCheck->http_status ?? '—' }} · {{ $latestCheck->response_ms }} мс
    @if($latestCheck->error)<div class="text-warning">{{ $latestCheck->error }}</div>@endif
    @if($latestCheck->certificate_expires_at)
        @php
            $certDays = now()->diffInDays(\Carbon\Carbon::parse($latestCheck->certificate_expires_at, 'UTC'), false);
        @endphp
        <div class="{{ $certDays <= 14 ? 'text-warning fw-semibold' : 'portal-soft' }}">TLS-сертифікат до {{ $formatDate($latestCheck->certificate_expires_at) }} ({{ $certDays }} днів).</div>
    @endif
    @if($latestCheck->timings)
    <details class="mt-2"><summary>Мережеві виміри</summary><div class="small">Час від початку HTTP-запиту, підсумований за переходами; DNS тут — дані транспорту після перевірки адреси.</div>
        @foreach(json_decode($latestCheck->timings, true) ?: [] as $key => $value)
        <span class="me-3">{{ ['namelookup_time'=>'DNS транспорту','connect_time'=>'До з’єднання','appconnect_time'=>'До TLS','starttransfer_time'=>'До першого байта','total_time'=>'Передача загалом'][$key] ?? $key }}: {{ $value }} мс</span>
        @endforeach
    </details>
    @endif
</div>
@endif
@include('portal.monitoring.report')
<div class="portal-card p-4 mb-3">
    <h2 class="h5">Інциденти</h2><p class="small portal-soft">Початок — перша невдала перевірка. Тривалість до закриття може містити прогалини спостереження; оцінений простій наведено у звіті вище.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Початок / підтверджено</th><th>Завершення</th><th>Тривалість у періоді</th><th>Причина / результат</th></tr></thead><tbody>
        @forelse($incidents as $incident)
        @php
            $incidentStart = \Carbon\CarbonImmutable::parse($incident->opened_at, 'UTC')->max($report['range']['from']);
            $incidentEnd = \Carbon\CarbonImmutable::parse($incident->closed_at ?? now(), 'UTC')->min($report['range']['to']);
        @endphp
        <tr><td>{{ $formatDate($incident->opened_at) }}<div class="small portal-soft">{{ $formatDate($incident->detected_at) }}</div></td><td>{{ $incident->closed_at ? $formatDate($incident->closed_at) : 'Триває' }}</td><td>{{ \App\Modules\Monitoring\Services\MonitoringReport::duration($incidentEnd->timestamp - $incidentStart->timestamp) }}</td><td>{{ $incident->error }} @if($incident->close_reason)<div class="small">{{ ['up'=>'Відновився','planned'=>'Планові роботи','paused'=>'Моніторинг призупинено','target_changed'=>'Змінено ціль перевірки','settings_changed'=>'Змінено налаштування','deleted'=>'Сайт у кошику','resumed'=>'Новий період спостереження'][$incident->close_reason] ?? $incident->close_reason }}</div>@endif</td></tr>
        @empty<tr><td colspan="4">Інцидентів у цьому періоді немає.</td></tr>@endforelse
    </tbody></table></div>{{ $incidents->links() }}
</div>
<div class="portal-card p-4 mb-3">
    <h2 class="h5">Останні перевірки</h2><p class="small portal-soft">Деталі зберігаються {{ config('monitoring.detail_days') }} днів. Часові підсумки зберігаються окремо. Ручні перевірки — лише діагностика.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Час / запуск</th><th>Результат</th><th>HTTP / мс</th><th>Адреса / причина</th></tr></thead><tbody>
        @forelse($checks as $check)<tr><td>{{ $formatDate($check->checked_at) }}<div class="small">{{ $check->manual ? 'Вручну' : 'Cron' }}</div></td><td>{{ $labels[$check->availability] ?? $check->availability }}@if($check->availability === 'planned')<div class="small">HTTP-стан: {{ $labels[$check->technical_availability] ?? '—' }}</div>@endif</td><td>{{ $check->http_status ?? '—' }} / {{ $check->response_ms }}</td><td class="text-break">{{ $check->checked_url }}<div class="small">{{ $check->error ?? '—' }}</div></td></tr>
        @empty<tr><td colspan="4">Немає збережених деталей за цей період.</td></tr>@endforelse
    </tbody></table></div>{{ $checks->links() }}
</div>
@can('monitoring.write')
<div class="portal-card p-4">
    <h2 class="h5">Налаштування цього сайту</h2>
    <form class="row g-3" method="POST" action="{{ route('portal.monitoring.site-settings', $site) }}">@csrf @method('PUT')
        @include('portal.monitoring.options')
        <div class="col-12"><h3 class="h6">Власні відповідальні</h3><p class="small portal-soft">Якщо нікого не вибрано, використовуються глобальні відповідальні.</p>
            <div class="row g-2">@forelse($recipients as $user)<div class="col-md-4"><label class="form-check"><input type="checkbox" class="form-check-input" name="monitoring_recipient_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, old('monitoring_recipient_ids', $site->monitoring_recipient_ids ?? [])))>{{ $user->displayName() }}</label></div>@empty<div>Немає активних підключених одержувачів.</div>@endforelse</div>
        </div>
        <div class="col-12"><button class="btn btn-primary">Зберегти налаштування</button></div>
    </form>
</div>
@endcan
@endsection
