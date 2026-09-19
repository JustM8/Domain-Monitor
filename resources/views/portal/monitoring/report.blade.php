@php
    $total = $report['total'];
    $duration = fn ($value) => \App\Modules\Monitoring\Services\MonitoringReport::duration($value);
    $range = $report['range'];
@endphp
<div class="portal-card p-4 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        @foreach(['search', 'environment', 'availability', 'enabled', 'mode'] as $filter)
            @if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <div class="col-md-3"><label class="form-label w-100">Період
            <select class="form-select" name="period">
                @foreach(['today' => 'Сьогодні', '7d' => '7 календарних днів', '30d' => '30 календарних днів', 'month' => 'Поточний місяць', 'all' => 'Увесь період', 'custom' => 'Власні дати'] as $value => $label)
                <option value="{{ $value }}" @selected($range['period'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label></div>
        <div class="col-md-2"><label class="form-label w-100">Від<input type="date" class="form-control" name="from" value="{{ request('from', $range['from']->toDateString()) }}"></label></div>
        <div class="col-md-2"><label class="form-label w-100">До<input type="date" class="form-control" name="to" value="{{ request('to', $range['to']->subSecond()->toDateString()) }}"></label></div>
        <div class="col-md-3"><label class="form-label w-100">Групування
            <select class="form-select" name="group">
                @foreach(['day' => 'За днями', 'week' => 'За тижнями', 'month' => 'За місяцями'] as $value => $label)
                <option value="{{ $value }}" @selected($range['group'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label></div>
        <div class="col-md-2 pb-2"><button class="btn btn-primary w-100">Показати</button></div>
    </form>
    <div class="small portal-soft mt-2">
        {{ $range['from']->format('d.m.Y H:i') }} — {{ $range['to']->format('d.m.Y H:i') }} · {{ config('monitoring.timezone') }}.
        Дати «Від/До» застосовуються для власного періоду. За періоди понад 400 днів денне групування змінюється на місячне.
        @if($range['first']) Часова історія від {{ \Carbon\Carbon::parse($range['first'], 'UTC')->timezone(config('monitoring.timezone'))->format('d.m.Y H:i') }}. @endif
    </div>
</div>
<div class="row g-3 mb-3">
    @foreach([
        'Доступність' => $total['uptime'] === null ? 'Немає даних' : $total['uptime'].'%',
        'Покриття перевірками' => $total['coverage'] === null ? 'Немає даних' : $total['coverage'].'%',
        'Оцінений простій' => $duration($total['down']),
        'Інциденти за період' => $report['incidents'],
        'Середня відповідь' => $total['average'] === null ? '—' : $total['average'].' мс',
        'p95 · верхня межа' => $total['p95'] ?? '—',
    ] as $label => $value)
    <div class="col-6 col-xl-2"><div class="portal-card p-3 h-100"><div class="small portal-soft">{{ $label }}</div><div class="fs-4 fw-semibold mt-1">{{ $value }}</div></div></div>
    @endforeach
</div>
<div class="portal-card p-4 mb-3">
    <div class="d-flex flex-wrap gap-3 small mb-3">
        <span>Немає даних: <strong>{{ $duration($total['unknown']) }}</strong></span>
        <span>Планові роботи: <strong>{{ $duration($total['planned']) }}</strong></span>
        <span>Найдовший спостережений збій: <strong>{{ $duration($report['longest']) }}</strong></span>
        <span>Максимальна успішна відповідь: <strong>{{ $total['samples'] ? $total['response_max'].' мс' : '—' }}</strong></span>
    </div>
    <p class="small portal-soft">Доступність — частка доступного часу серед спостереженого, без планових робіт. Покриття показує, яку частину запланованого часу вдалося спостерігати. Через два інтервали без перевірки починається «немає даних». Час оцінений за періодичними перевірками. Для кількох сайтів тривалості підсумовуються. Швидкість — лише успішні автоматичні перевірки; p95 оцінений за гістограмою.</p>
    @if($total['expected'] > 0)
    <h2 class="h6">Доступність за період</h2>
    <div class="d-flex gap-1 align-items-stretch mb-2" style="height:90px;overflow:hidden" role="img" aria-label="Шкала доступності; точні значення у таблиці нижче">
        @foreach($report['buckets'] as $bucket)
        <div class="d-flex flex-column-reverse flex-fill overflow-hidden rounded" style="min-width:0" title="{{ $bucket['label'] }}: доступність {{ $bucket['uptime'] ?? '—' }}%, покриття {{ $bucket['coverage'] ?? '—' }}%">
            @foreach(['up' => '#198754', 'down' => '#dc3545', 'planned' => '#6c757d', 'unknown' => '#e9ecef'] as $stateName => $color)
            <div style="background:{{ $color }};height:{{ $bucket['expected'] ? 100 * $bucket[$stateName] / $bucket['expected'] : ($stateName === 'unknown' ? 100 : 0) }}%"></div>
            @endforeach
        </div>
        @endforeach
    </div>
    <div class="small mb-4">🟢 Доступний · 🔴 Недоступний · Сірий — планові роботи · Світлий — немає даних</div>
    @endif
    @if($total['samples'])
    @php
        $maxLatency = max(1, collect($report['buckets'])->max('average') ?? 1);
        $count = count($report['buckets']);
        $points = [];
        foreach ($report['buckets'] as $i => $bucket) {
            if ($bucket['average'] !== null) {
                $points[] = ['x' => 20 + ($count > 1 ? 960 * $i / ($count - 1) : 480),
                    'y' => 145 - 120 * $bucket['average'] / $maxLatency, 'bucket' => $bucket];
            }
        }
    @endphp
    <h2 class="h6">Середня відповідь, мс</h2>
    <svg viewBox="0 0 1000 170" role="img" aria-label="Графік середнього часу відповіді; точні значення у таблиці" style="width:100%;max-height:220px">
        <line x1="20" y1="145" x2="980" y2="145" stroke="#adb5bd"/>
        @foreach($points as $point)
        <line x1="{{ $point['x'] }}" y1="145" x2="{{ $point['x'] }}" y2="{{ $point['y'] }}" stroke="#0d6efd" stroke-width="3"/>
        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#0d6efd"><title>{{ $point['bucket']['label'] }}: {{ $point['bucket']['average'] }} мс</title></circle>
        @endforeach
        <text x="20" y="165" font-size="12" fill="currentColor">{{ $report['buckets'][0]['label'] }}</text>
        <text x="980" y="165" text-anchor="end" font-size="12" fill="currentColor">{{ $report['buckets'][$count - 1]['label'] }}</text>
    </svg>
    @endif
    <div class="table-responsive" style="max-height:520px">
        <table class="table table-sm align-middle"><caption>Статистика автоматичних перевірок за вибраний період</caption>
            <thead><tr><th>Період</th><th>Доступність</th><th>Покриття</th><th>Простій</th><th>Немає даних</th><th>Середня</th><th>p95 ≤</th></tr></thead>
            <tbody>@forelse($report['buckets'] as $bucket)
            <tr><td>{{ $bucket['label'] }}</td><td>{{ $bucket['uptime'] === null ? '—' : $bucket['uptime'].'%' }}</td><td>{{ $bucket['coverage'] === null ? '—' : $bucket['coverage'].'%' }}</td><td>{{ $duration($bucket['down']) }}</td><td>{{ $duration($bucket['unknown']) }}</td><td>{{ $bucket['average'] === null ? '—' : $bucket['average'].' мс' }}</td><td>{{ $bucket['p95'] ?? '—' }}</td></tr>
            @empty<tr><td colspan="7">Немає даних за вибраний період.</td></tr>@endforelse</tbody>
        </table>
    </div>
</div>
