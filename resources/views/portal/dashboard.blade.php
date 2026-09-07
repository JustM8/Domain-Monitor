@extends('layouts.portal')

@section('page_title', __('portal.dashboard'))
@section('page_subtitle', '')

@section('content')
@php
    $projectPalette = ['#7a8491', '#8f829a', '#76897f'];
    $projectMax = max(1, ...$siteTypeCounts->toArray());
    $statusMax = max(1, ...$statusCounts->toArray());
@endphp

<div class="portal-card p-3 p-xl-4 mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <h1 class="h4 fw-semibold mb-0">{{ __('portal.dashboard') }}</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary btn-sm px-3" href="{{ route('portal.sites.index') }}">
                <i class="bi bi-plus-circle me-1"></i>{{ __('portal.add_site') }}
            </a>
            @if(auth()->user()?->isAdmin() || auth()->user()?->isPm())
                <a class="btn btn-outline-secondary btn-sm px-3" href="{{ route('portal.statuses.index') }}">
                    <i class="bi bi-tags me-1"></i>{{ __('portal.statuses_settings') }}
                </a>
            @endif
            @if(auth()->user()?->isAdmin())
                <a class="btn btn-outline-secondary btn-sm px-3" href="{{ route('portal.activity.index') }}">
                    <i class="bi bi-journal-text me-1"></i>{{ __('portal.activity_log') }}
                </a>
            @endif
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-xl-3">
        <div class="portal-card portal-stat-card h-100 py-3">
            <div>
                <div class="portal-soft small">{{ __('portal.sites') }}</div>
                <div class="fs-4 fw-bold lh-1 mt-2">{{ $sitesCount }}</div>
            </div>
            <div class="portal-stat-icon"><i class="bi bi-globe2"></i></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="portal-card portal-stat-card h-100 py-3">
            <div>
                <div class="portal-soft small">{{ __('portal.active_sites') }}</div>
                <div class="fs-4 fw-bold text-success lh-1 mt-2">{{ $activeSitesCount }}</div>
            </div>
            <div class="portal-stat-icon"><i class="bi bi-check2-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="portal-card portal-stat-card h-100 py-3">
            <div>
                <div class="portal-soft small">{{ __('portal.disabled_sites') }}</div>
                <div class="fs-4 fw-bold text-danger lh-1 mt-2">{{ $disabledSitesCount }}</div>
            </div>
            <div class="portal-stat-icon"><i class="bi bi-pause-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="portal-card portal-stat-card h-100 py-3">
            <div>
                <div class="portal-soft small">{{ __('portal.missing_data') }}</div>
                <div class="fs-4 fw-bold lh-1 mt-2">{{ $sitesWithoutCompanyCount + $sitesWithoutStatusCount }}</div>
            </div>
            <div class="portal-stat-icon"><i class="bi bi-exclamation-diamond"></i></div>
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-xl-5">
        <div class="portal-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">{{ __('portal.recent_sites') }}</h2>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.sites.index') }}">{{ __('portal.open') }}</a>
            </div>

            @if($recentSites->isEmpty())
                <div class="portal-empty">{{ __('portal.no_sites_yet') }}</div>
            @else
                <div class="vstack gap-2">
                    @foreach($recentSites as $site)
                        <div class="portal-list-item py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <div class="portal-stat-icon flex-shrink-0" style="width: 36px; height: 36px; border-radius: 12px;">
                                        <i class="bi bi-globe2"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <div class="fw-semibold">{{ $site->name }}</div>
                                            <span class="badge rounded-pill text-bg-{{ $site->is_active ? 'success' : 'danger' }}">
                                                {{ $site->is_active ? __('portal.active') : __('portal.disabled') }}
                                            </span>
                                        </div>
                                        <div class="portal-soft small">
                                            {{ parse_url($site->url, PHP_URL_HOST) ?: $site->url }}
                                            @if($site->status)
                                                <span class="mx-1">&middot;</span>{{ $site->status->name }}
                                            @endif
                                            <span class="mx-1">&middot;</span>{{ $site->siteTypeLabel() }} / {{ $site->environmentLabel() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ $site->url }}">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <a class="btn btn-sm btn-primary" href="{{ route('portal.sites.show', $site) }}">{{ __('portal.details') }}</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="col-xl-7">
        <div class="portal-card p-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                <h2 class="h6 mb-0">{{ __('portal.status_overview') }}</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary active" type="button" data-preview-target="variant-list">
                        1
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-preview-target="variant-bars">
                        2
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-preview-target="variant-cards">
                        3
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-preview-target="variant-horizontal-chart">
                        4
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-preview-target="variant-stacked-chart">
                        5
                    </button>
                    @if(auth()->user()?->isAdmin())
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.activity.index') }}">
                            <i class="bi bi-journal-text me-1"></i>{{ __('portal.activity_log') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="portal-soft small mb-3">
                {{ __('portal.dashboard_modes_hint') }}
            </div>

            <div class="portal-preview-pane" data-preview-pane="variant-list">
                <div class="portal-list-item">
                    <div class="small fw-semibold mb-3">{{ __('portal.dashboard_variant_1') }}</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small fw-semibold mb-2">{{ __('portal.site_product_type') }}</div>
                            <div class="vstack gap-2">
                                @foreach($siteTypeLabels as $index => $label)
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $projectPalette[$index] ?? '#94a3b8' }};"></span>
                                            <span class="small">{{ $label }}</span>
                                        </div>
                                        <span class="small fw-semibold">{{ $siteTypeCounts[$index] ?? 0 }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small fw-semibold mb-2">{{ __('portal.statuses') }}</div>
                            <div class="vstack gap-2">
                                @foreach($statusLegend as $index => $status)
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $status->color }};"></span>
                                            <span class="small">{{ $status->name }}</span>
                                        </div>
                                        <span class="small fw-semibold">{{ $statusCounts[$index] ?? 0 }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small fw-semibold mb-2">{{ __('portal.missing_data') }}</div>
                            <div class="vstack gap-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="small">{{ __('portal.missing_company_count') }}</span>
                                    <span class="small fw-semibold">{{ $sitesWithoutCompanyCount }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="small">{{ __('portal.missing_status_count') }}</span>
                                    <span class="small fw-semibold">{{ $sitesWithoutStatusCount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="portal-preview-pane d-none" data-preview-pane="variant-bars">
                <div class="portal-list-item">
                    <div class="small fw-semibold mb-3">{{ __('portal.dashboard_variant_2') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small fw-semibold mb-2">{{ __('portal.site_product_type') }}</div>
                            <div class="vstack gap-2">
                                @foreach($siteTypeLabels as $index => $label)
                                    @php
                                        $projectValue = $siteTypeCounts[$index] ?? 0;
                                        $projectWidth = $projectMax > 0 ? ($projectValue / $projectMax) * 100 : 0;
                                    @endphp
                                    <div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>{{ $label }}</span>
                                            <span>{{ $projectValue }}</span>
                                        </div>
                                        <div style="height: 8px; background: rgba(148, 163, 184, 0.14); border-radius: 999px; overflow: hidden;">
                                            <div style="width: {{ $projectWidth }}%; height: 100%; background: {{ $projectPalette[$index] ?? '#94a3b8' }};"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="small fw-semibold mb-2">{{ __('portal.statuses') }}</div>
                            <div class="vstack gap-2">
                                @foreach($statusLegend as $index => $status)
                                    @php
                                        $statusValue = $statusCounts[$index] ?? 0;
                                        $statusWidth = $statusMax > 0 ? ($statusValue / $statusMax) * 100 : 0;
                                    @endphp
                                    <div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>{{ $status->name }}</span>
                                            <span>{{ $statusValue }}</span>
                                        </div>
                                        <div style="height: 8px; background: rgba(148, 163, 184, 0.14); border-radius: 999px; overflow: hidden;">
                                            <div style="width: {{ $statusWidth }}%; height: 100%; background: {{ $status->color }};"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="portal-preview-pane d-none" data-preview-pane="variant-cards">
                <div class="portal-list-item">
                    <div class="small fw-semibold mb-3">{{ __('portal.dashboard_variant_3') }}</div>
                    <div class="row g-2">
                        @foreach($siteTypeLabels as $index => $label)
                            <div class="col-sm-6 col-lg-4">
                                <div class="portal-chart-wrap h-100">
                                    <div class="small portal-soft mb-1">{{ __('portal.site_product_type') }}</div>
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $projectPalette[$index] ?? '#94a3b8' }};"></span>
                                            <span class="small fw-semibold">{{ $label }}</span>
                                        </div>
                                        <span class="fw-bold">{{ $siteTypeCounts[$index] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @foreach($statusLegend as $index => $status)
                            <div class="col-sm-6 col-lg-4">
                                <div class="portal-chart-wrap h-100">
                                    <div class="small portal-soft mb-1">{{ __('portal.statuses') }}</div>
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $status->color }};"></span>
                                            <span class="small fw-semibold">{{ $status->name }}</span>
                                        </div>
                                        <span class="fw-bold">{{ $statusCounts[$index] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="col-sm-6 col-lg-4">
                            <div class="portal-chart-wrap h-100">
                                <div class="small portal-soft mb-1">{{ __('portal.missing_company_count') }}</div>
                                <div class="fw-bold">{{ $sitesWithoutCompanyCount }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="portal-chart-wrap h-100">
                                <div class="small portal-soft mb-1">{{ __('portal.missing_status_count') }}</div>
                                <div class="fw-bold">{{ $sitesWithoutStatusCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="portal-preview-pane d-none" data-preview-pane="variant-horizontal-chart">
                <div class="portal-list-item">
                    <div class="small fw-semibold mb-3">{{ __('portal.dashboard_variant_4') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="portal-chart-wrap" style="height: 240px;">
                                <div class="small fw-semibold mb-2">{{ __('portal.site_product_type') }}</div>
                                <canvas id="projectHorizontalChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="portal-chart-wrap" style="height: 240px;">
                                <div class="small fw-semibold mb-2">{{ __('portal.statuses') }}</div>
                                <canvas id="statusHorizontalChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="portal-preview-pane d-none" data-preview-pane="variant-stacked-chart">
                <div class="portal-list-item">
                    <div class="small fw-semibold mb-3">{{ __('portal.dashboard_variant_5') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="portal-chart-wrap" style="height: 220px;">
                                <div class="small fw-semibold mb-2">{{ __('portal.site_product_type') }}</div>
                                <canvas id="projectStackedChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="portal-chart-wrap" style="height: 220px;">
                                <div class="small fw-semibold mb-2">{{ __('portal.statuses') }}</div>
                                <canvas id="statusStackedChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const previewButtons = document.querySelectorAll('[data-preview-target]');
    const previewPanes = document.querySelectorAll('[data-preview-pane]');

    const setPreview = (target) => {
        previewButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.previewTarget === target);
        });

        previewPanes.forEach((pane) => {
            pane.classList.toggle('d-none', pane.dataset.previewPane !== target);
        });

        window.setTimeout(() => {
            chartInstances.forEach((chart) => chart.resize());
        }, 50);
    };

    previewButtons.forEach((button) => {
        button.addEventListener('click', () => setPreview(button.dataset.previewTarget));
    });

    const projectLabels = @json($siteTypeLabels);
    const projectCounts = @json($siteTypeCounts);
    const projectPalette = ['#7a8491', '#8f829a', '#76897f'];
    const statusLabels = @json($statusLabels);
    const statusCounts = @json($statusCounts);
    const rawStatusColors = @json($statusColors);
    const softenedStatusColors = rawStatusColors.map((color) => {
        if (!/^#([0-9a-f]{6})$/i.test(color)) {
            return color;
        }

        const hex = color.replace('#', '');
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);

        return `rgba(${r}, ${g}, ${b}, 0.82)`;
    });

    const chartInstances = [];

    const commonBarOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: 'rgba(148, 163, 184, 0.12)' }
            },
            y: {
                grid: { display: false }
            }
        }
    };

    const projectHorizontalCtx = document.getElementById('projectHorizontalChart');
    if (projectHorizontalCtx) {
        chartInstances.push(new Chart(projectHorizontalCtx, {
            type: 'bar',
            data: {
                labels: projectLabels,
                datasets: [{
                    data: projectCounts,
                    backgroundColor: projectPalette,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                ...commonBarOptions,
                indexAxis: 'y'
            }
        }));
    }

    const statusHorizontalCtx = document.getElementById('statusHorizontalChart');
    if (statusHorizontalCtx) {
        chartInstances.push(new Chart(statusHorizontalCtx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: softenedStatusColors,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                ...commonBarOptions,
                indexAxis: 'y'
            }
        }));
    }

    const projectStackedCtx = document.getElementById('projectStackedChart');
    if (projectStackedCtx) {
        chartInstances.push(new Chart(projectStackedCtx, {
            type: 'bar',
            data: {
                labels: [''],
                datasets: projectLabels.map((label, index) => ({
                    label,
                    data: [projectCounts[index] ?? 0],
                    backgroundColor: projectPalette[index] ?? '#94a3b8',
                    borderRadius: index === projectLabels.length - 1 ? 8 : 0,
                    borderSkipped: false,
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10 }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        display: false,
                        grid: { display: false }
                    },
                    y: {
                        stacked: true,
                        display: false,
                        grid: { display: false }
                    }
                }
            }
        }));
    }

    const statusStackedCtx = document.getElementById('statusStackedChart');
    if (statusStackedCtx) {
        chartInstances.push(new Chart(statusStackedCtx, {
            type: 'bar',
            data: {
                labels: [''],
                datasets: statusLabels.map((label, index) => ({
                    label,
                    data: [statusCounts[index] ?? 0],
                    backgroundColor: softenedStatusColors[index] ?? '#94a3b8',
                    borderRadius: index === statusLabels.length - 1 ? 8 : 0,
                    borderSkipped: false,
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10 }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        display: false,
                        grid: { display: false }
                    },
                    y: {
                        stacked: true,
                        display: false,
                        grid: { display: false }
                    }
                }
            }
        }));
    }
</script>
@endpush
