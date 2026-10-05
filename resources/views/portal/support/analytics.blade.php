@extends('layouts.portal')

@section('page_title', __('portal.support.analytics'))
@section('page_subtitle', 'Загальні метрики по зверненнях, оцінках, групах і менеджерах.')

@push('styles')
<style>
    .support-analytics-hero {
        background:
            radial-gradient(circle at top right, rgba(138, 103, 80, 0.14), transparent 28%),
            radial-gradient(circle at bottom left, rgba(81, 105, 125, 0.12), transparent 24%),
            var(--portal-surface);
    }

    .support-analytics-kpi {
        min-height: 108px;
    }

    .support-analytics-kpi .value {
        letter-spacing: -0.04em;
        line-height: 1;
    }

    .support-analytics-chart {
        min-height: 330px;
    }

    .support-analytics-chart canvas {
        width: 100% !important;
        height: 280px !important;
    }

    .support-analytics-table thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--portal-muted);
        border-bottom: 1px solid var(--portal-border);
        background: rgba(255, 255, 255, .04);
    }

    .support-analytics-table tbody tr:hover {
        background: rgba(255, 255, 255, .025);
    }

    .support-analytics-subtle {
        color: var(--portal-muted);
    }
</style>
@endpush

@section('content')
<div class="portal-card p-4 mb-3 support-analytics-hero">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1">Support аналітика</h1>
            <div class="support-analytics-subtle small">Фактична робота менеджерів за підтвердженими Telegram-акаунтами.</div>
        </div>
        @can('support.read')<a class="btn btn-outline-secondary" href="{{ route('portal.support.index') }}">До списку звернень</a>@endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">Всього</div>
            <div class="fs-2 fw-bold value">{{ $totals['all'] }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">Відкриті</div>
            <div class="fs-2 fw-bold value">{{ $totals['open'] }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">В роботі</div>
            <div class="fs-2 fw-bold value">{{ $totals['in_progress'] }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">Закриті</div>
            <div class="fs-2 fw-bold value">{{ $totals['closed'] }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">PM</div>
            <div class="fs-2 fw-bold value">{{ $totals['pm'] }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="portal-card p-3 support-analytics-kpi h-100">
            <div class="support-analytics-subtle small mb-2">Середня оцінка</div>
            <div class="fs-2 fw-bold value">{{ $totals['avg_rating'] }}</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6 col-xxl-3">
        <div class="portal-card p-4 support-analytics-chart h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Статуси</h2>
                    <div class="support-analytics-subtle small">Як розподіляються звернення по станах.</div>
                </div>
            </div>
            <div class="support-analytics-chart">
                <canvas id="support-analytics-status-chart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-xxl-3">
        <div class="portal-card p-4 support-analytics-chart h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Оцінки</h2>
                    <div class="support-analytics-subtle small">Рейтинг клієнтів від 0 до 5.</div>
                </div>
            </div>
            <div class="support-analytics-chart">
                <canvas id="support-analytics-ratings-chart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-xxl-3">
        <div class="portal-card p-4 support-analytics-chart h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Топ груп</h2>
                    <div class="support-analytics-subtle small">Навантаження та закриття по групах.</div>
                </div>
            </div>
            <div class="support-analytics-chart">
                <canvas id="support-analytics-topics-chart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-xxl-3">
        <div class="portal-card p-4 support-analytics-chart h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Топ менеджери</h2>
                    <div class="support-analytics-subtle small">Реальні відповіді, тікети й закриття менеджерів.</div>
                </div>
            </div>
            <div class="support-analytics-chart">
                <canvas id="support-analytics-managers-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="portal-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0 support-analytics-table">
            <thead>
            <tr>
                <th>Відповідальний</th>
                <th>Telegram</th>
                <th>Напрямки</th>
                <th>Тікети</th>
                <th>Відповіді</th>
                <th>Перші відповіді</th>
                <th>Закриті ним</th>
                <th>Сер. перша відповідь</th>
                <th>Середня оцінка</th>
            </tr>
            </thead>
            <tbody>
            @forelse($byManager as $row)
                <tr>
                    <td class="fw-semibold">
                        {{ $row['name'] }}
                        @if(! empty($row['is_unverified']))
                            <span class="badge rounded-pill text-bg-warning ms-2">не верифіковано</span>
                        @endif
                    </td>
                    <td>{{ $row['telegram'] ?? __('portal.empty') }}</td>
                    <td>{{ $row['topics'] }}</td>
                    <td>{{ $row['tickets'] }}</td>
                    <td>{{ $row['replies'] }}</td>
                    <td>{{ $row['first_responses'] }}</td>
                    <td>{{ $row['closed'] }}</td>
                    <td>{{ $row['avg_first_response'] }}</td>
                    <td>{{ $row['avg_rating'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="support-analytics-subtle">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script id="support-analytics-data" type="application/json">@json($chartData)</script>
@endsection
