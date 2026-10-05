@extends('layouts.portal')
@section('page_title', 'Моніторинг')
@section('page_subtitle', 'Доступність, швидкість відповіді та історія збоїв')
@section('content')
<div class="portal-card p-4 mb-3">
    <h2 class="h5">Автоматичні перевірки</h2>
    @if($lastRun)
    @php
        $lastRunAt = \Carbon\Carbon::parse($lastRun->started_at, config('monitoring.timezone'));
    @endphp
    <div>Останній запуск: <strong>{{ $lastRunAt->format('d.m.Y H:i') }}</strong> · {{ ['running' => 'виконується', 'completed' => 'завершено', 'failed' => 'помилка', 'interrupted' => 'перервано'][$lastRun->status] ?? $lastRun->status }} · У цьому запуску перевірено: {{ $lastRun->checked }}</div>
    @if($lastRunAt->lt(now(config('monitoring.timezone'))->subMinutes(5)))<div class="text-warning mt-1">Cron давно не запускався. Перевірте розклад на хостингу.</div>@endif
    @if($lastRun->error)<div class="text-danger">Помилка: {{ $lastRun->error }}</div>@endif
    @else<div class="text-warning">Ще не було запусків. Налаштуйте cron за інструкцією розгортання.</div>@endif
    <div class="small portal-soft mt-2">Cron увімкнено: {{ $enabledSitesCount }}. Очікують перевірки зараз: {{ $dueSitesCount }}. Очікують сповіщення: {{ $pendingNotifications }}. Інтервал і поріг невдач задаються окремо для кожного сайту.</div>
</div>
<div class="portal-card p-4 mb-3">
    <form method="GET" class="row g-2 mb-3">
        @foreach(['period','from','to','group'] as $filter)
        @if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <div class="col-md-3"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Назва або адреса" aria-label="Пошук сайту"></div>
        <div class="col-md-2"><select name="environment" class="form-select" aria-label="Середовище"><option value="">Prod і Dev</option><option value="prod" @selected(request('environment') === 'prod')>Prod</option><option value="dev" @selected(request('environment') === 'dev')>Dev</option></select></div>
        <div class="col-md-2"><select name="availability" class="form-select" aria-label="Доступність"><option value="">Усі стани</option>@foreach(['up' => 'Доступний', 'down' => 'Недоступний', 'planned' => 'Планові роботи', 'unknown' => 'Немає свіжих даних', 'stale' => 'Прострочено'] as $value => $label)<option value="{{ $value }}" @selected(request('availability') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="enabled" class="form-select" aria-label="Автоматичні перевірки"><option value="">Усі перевірки</option><option value="1" @selected(request('enabled') === '1')>Cron увімкнено</option><option value="0" @selected(request('enabled') === '0')>Cron вимкнено</option></select></div>
        <div class="col-md-2"><select name="mode" class="form-select" aria-label="Режим"><option value="">Усі режими</option><option value="0" @selected(request('mode') === '0')>Лише моніторинг</option><option value="1" @selected(request('mode') === '1')>З керуванням</option></select></div>
        <div class="col-md-1"><button class="btn btn-primary w-100" aria-label="Знайти">→</button></div>
    </form>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Сайт / режим</th><th>Cron</th><th>Останній автоматичний стан</th><th>HTTP / час</th><th>Перевірено</th><th></th></tr></thead><tbody>
        @forelse($sites as $site)
        @php
            $stale = $site->last_checked_at && \Carbon\Carbon::parse($site->last_checked_at, 'UTC')->addMinutes(($site->observed_interval ?: $site->monitoring_interval) * 2)->lt(now());
            $overdue = $site->monitoring_enabled && $site->next_check_at && \Carbon\Carbon::parse($site->next_check_at, 'UTC')->addMinute()->lt(now());
            $displayState = $stale ? 'unknown' : ($site->availability ?? 'unknown');
        @endphp
        <tr>
            <td><a class="fw-semibold" href="{{ route('portal.monitoring.show', $site) }}">{{ $site->name }}</a><div class="small portal-soft text-break">{{ $site->url }}</div><div class="small">{{ strtoupper($site->environment) }} · {{ $site->remote_control_enabled ? 'Моніторинг і керування' : 'Лише моніторинг' }}</div></td>
            <td>{{ $site->monitoring_enabled ? 'Кожні '.$site->monitoring_interval.' хв' : 'Пауза' }}@if($overdue)<div class="text-warning small">Перевірка прострочена</div>@endif</td>
            <td><span class="badge text-bg-{{ ['up' => 'success','down' => 'danger','planned' => 'secondary'][$displayState] ?? 'light' }}">{{ ['up' => 'Доступний','down' => 'Недоступний','planned' => 'Планові роботи','unknown' => 'Немає свіжих даних'][$displayState] }}</span>
            @if($displayState === 'down' && $site->consecutive_failures < $site->monitoring_failure_threshold)<div class="small portal-soft">Очікуємо підтвердження</div>@endif</td>
            <td>{{ $site->http_status ?? '—' }} / {{ $site->response_ms ?? '—' }} мс</td>
            <td class="small">{{ $site->last_checked_at ? \Carbon\Carbon::parse($site->last_checked_at, 'UTC')->timezone(config('monitoring.timezone'))->format('d.m H:i:s') : '—' }}</td>
            <td><form method="POST" action="{{ route('portal.monitoring.check', $site) }}">@csrf<button class="btn btn-sm btn-outline-primary">Перевірити</button></form></td>
        </tr>
        @empty<tr><td colspan="6">Сайтів за цими фільтрами немає.</td></tr>@endforelse
    </tbody></table></div>{{ $sites->links() }}
</div>
<h2 class="h5">Звіт за всіма сайтами вибраного фільтра</h2>
@include('portal.monitoring.report')
@can('monitoring.write')
<div class="portal-card p-4">
    <h2 class="h5">Глобальні відповідальні</h2><p class="portal-soft">Для сайтів без власного списку відповідальних. Одержувачі — активні Admin/PM із підключеним Access-ботом.</p>
    <form method="POST" action="{{ route('portal.monitoring.settings') }}">@csrf
        <div class="row g-2 mb-3">@forelse($recipients as $user)<div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="recipient_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, $selectedRecipients, true))><span class="form-check-label">{{ $user->displayName() }}</span></label></div>@empty<div>Поки немає підключених одержувачів.</div>@endforelse</div>
        <button class="btn btn-primary">Зберегти відповідальних</button>
    </form>
</div>
@endcan
@endsection
