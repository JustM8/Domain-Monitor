@extends('layouts.portal')
@section('page_title', 'Моніторинг')
@section('page_subtitle', 'Доступність сайтів, історія перевірок та відповідальні')
@section('content')
<div class="row g-3 mb-3">
    @foreach(['Prod' => $summary['prod'] ?? 0, 'Dev' => $summary['dev'] ?? 0, 'Доступні Prod' => $availability['up'] ?? 0, 'Інциденти' => $openIncidents, 'Планово вимкнені' => $availability['planned'] ?? 0, 'Успішні перевірки · 30 днів' => $uptime === null ? '—' : $uptime.'%'] as $label => $value)
    <div class="col-6 col-xl-2"><div class="portal-card p-3 h-100"><div class="portal-soft small">{{ $label }}</div><div class="fs-3 fw-semibold">{{ $value }}</div></div></div>
    @endforeach
</div>
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between flex-wrap gap-3">
        <div><h2 class="h5">Автоматичні перевірки</h2>
            @if($lastRun)
            <div>Останній запуск: <strong>{{ $lastRun->started_at }}</strong> · {{ ['running' => 'виконується', 'completed' => 'завершено', 'failed' => 'помилка', 'interrupted' => 'перервано'][$lastRun->status] ?? $lastRun->status }} · Перевірено: {{ $lastRun->checked }}</div>
            @if(\Carbon\Carbon::parse($lastRun->started_at)->lt(now()->subMinutes(15)))<div class="text-warning mt-1">Дані можуть бути застарілими. Перевірте cron на хостингу.</div>@endif
            @if($lastRun->error)<div class="text-danger">Помилка: {{ $lastRun->error }}</div>@endif
            @else<div class="portal-soft">Ще не було запусків. Налаштуйте cron за інструкцією розгортання.</div>@endif
        </div><span class="portal-chip align-self-start">Очікують сповіщення: {{ $pendingNotifications }}</span>
    </div>
    <div class="small portal-soft mt-2">Prod перевіряються автоматично. Dev — вручну. Сповіщення після двох невдач поспіль. Відсоток показує частку успішних перевірок, без планових вимкнень; це не безперервний замір uptime.</div>
</div>
<div class="portal-card p-4 mb-3">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Назва або адреса сайту" aria-label="Пошук сайту"></div>
        <div class="col-md-3"><select name="environment" class="form-select" aria-label="Середовище"><option value="">Усі середовища</option value="prod" @selected(request('environment') === 'prod')>Prod</option><option value="dev" @selected(request('environment') === 'dev')>Dev</option></select></div>
        <div class="col-md-3"><select name="availability" class="form-select" aria-label="Доступність"><option value="">Усі стани</option value="up" @selected(request('availability') === 'up')>Доступний</option><option value="down" @selected(request('availability') === 'down')>Недоступний</option><option value="planned" @selected(request('availability') === 'planned')>Планово вимкнений</option><option value="unknown" @selected(request('availability') === 'unknown')>Ще не перевірено</option></select></div>
        <div class="col-md-1"><button class="btn btn-primary w-100" aria-label="Знайти"><i class="bi bi-search"></i></button></div>
    </form>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Сайт</th><th>Середовище</th><th>Доступність</th><th>HTTP / час</th><th>Перевірено</th><th></th></tr></thead><tbody>
    @forelse($sites as $site)
    <tr><td><a href="{{ route('portal.monitoring.show', $site) }}" class="fw-semibold">{{ $site->name }}</a><div class="small portal-soft text-break">{{ $site->url }}</div></td>
    <td>{{ strtoupper($site->environment) }} · {{ $site->siteTypeLabel() }} @if($site->display_mode === 'iframe')<span class="badge text-bg-light">iframe</span>@endif</td>
    <td><span class="badge text-bg-{{ ['up' => 'success', 'down' => 'danger', 'planned' => 'secondary'][$site->availability] ?? 'light' }}">{{ ['up' => 'Доступний', 'down' => 'Недоступний', 'planned' => 'Планово вимкнений'][$site->availability] ?? 'Не перевірено' }}</span>
    @if($site->availability === 'down' && $site->consecutive_failures < 2)<div class="small portal-soft">Очікуємо повторну перевірку</div>@endif</td>
    <td>{{ $site->http_status ?? '—' }} / {{ $site->response_ms ?? '—' }} мс</td><td class="small">{{ $site->last_checked_at ?? '—' }}</td>
    <td><form method="POST" action="{{ route('portal.monitoring.check', $site) }}">@csrf<button class="btn btn-sm btn-outline-primary">Перевірити</button></form></td></tr>
    @empty<tr><td colspan="6" class="portal-soft">Сайтів за цими фільтрами немає.</td></tr>@endforelse
    </tbody></table></div>{{ $sites->links() }}
</div>
<div class="portal-card p-4">
    <h2 class="h5">Відповідальні</h2><p class="portal-soft">Оберіть активних Admin/PM з підключеним Access-ботом. Вони отримуватимуть повідомлення про падіння та відновлення.</p>
    <form method="POST" action="{{ route('portal.monitoring.settings') }}">@csrf
        <div class="row g-2 mb-3">@forelse($recipients as $user)<div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="recipient_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, $selectedRecipients, true))><span class="form-check-label">{{ $user->displayName() }} <small class="portal-soft">{{ $user->role->label }}</small></span></label></div>@empty<div class="portal-soft">Поки немає підключених одержувачів.</div>@endforelse</div>
        <button class="btn btn-primary">Зберегти відповідальних</button>
    </form>
</div>
@endsection
