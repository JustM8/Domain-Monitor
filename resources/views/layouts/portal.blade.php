<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('portal.title') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/js/app.js'])
    <style>
        :root,
        body[data-theme="light"] {
            --portal-bg: linear-gradient(180deg, #ebe9e2 0%, #e2e0d8 100%);
            --portal-surface: rgba(247, 245, 239, 0.94);
            --portal-surface-strong: #f5f2ec;
            --portal-border: rgba(92, 88, 80, 0.14);
            --portal-text: #232831;
            --portal-muted: #6e746f;
            --portal-primary: #51697d;
            --portal-primary-soft: rgba(81, 105, 125, 0.1);
            --portal-accent: #8a6750;
            --portal-sidebar-bg: linear-gradient(180deg, #252932, #1f232b);
            --portal-sidebar-border: rgba(255, 255, 255, 0.06);
            --portal-sidebar-text: #f2f1ec;
            --portal-sidebar-muted: rgba(242, 241, 236, 0.62);
        }

        body[data-theme="dark"] {
            --portal-bg: linear-gradient(180deg, #151920 0%, #10141b 100%);
            --portal-surface: rgba(22, 28, 38, 0.94);
            --portal-surface-strong: #1a2230;
            --portal-border: rgba(142, 155, 179, 0.12);
            --portal-text: #e8edf2;
            --portal-muted: #93a0b3;
            --portal-primary: #8ea2b9;
            --portal-primary-soft: rgba(142, 162, 185, 0.12);
            --portal-accent: #b38b70;
            --portal-sidebar-bg: linear-gradient(180deg, #11161e, #0d1219);
            --portal-sidebar-border: rgba(255, 255, 255, 0.05);
            --portal-sidebar-text: #edf1f5;
            --portal-sidebar-muted: rgba(237, 241, 245, 0.58);
        }

        html, body { min-height: 100%; }

        body.portal-body {
            background: var(--portal-bg);
            color: var(--portal-text);
            font-family: "Manrope", "Segoe UI", sans-serif;
            font-feature-settings: "liga" 1, "kern" 1;
            text-rendering: optimizeLegibility;
        }

        .portal-shell { min-height: 100vh; }

        .portal-sidebar {
            width: 280px;
            background: var(--portal-sidebar-bg);
            color: var(--portal-sidebar-text);
            border-right: 1px solid var(--portal-sidebar-border);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .portal-sidebar .nav-link {
            color: var(--portal-sidebar-text);
            border-radius: 8px;
            padding: .62rem .7rem;
            margin-bottom: .2rem;
            font-size: .96rem;
        }

        .portal-sidebar .nav-link:hover,
        .portal-sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .portal-main {
            min-width: 0;
            flex: 1;
        }

        .portal-topbar {
            background: var(--portal-surface);
            border: 1px solid var(--portal-border);
            border-radius: 14px;
            box-shadow: 0 8px 22px rgba(41, 35, 29, 0.05);
            position: sticky;
            top: .75rem;
            z-index: 20;
        }

        .portal-card,
        .portal-surface,
        .portal-dropdown {
            background: var(--portal-surface);
            border: 1px solid var(--portal-border);
            box-shadow: 0 8px 22px rgba(41, 35, 29, 0.04);
            border-radius: 14px;
        }

        .portal-soft { color: var(--portal-muted); }

        .portal-brand {
            color: var(--portal-sidebar-text);
            text-decoration: none;
        }

        .portal-brand-title,
        .portal-page-title {
            font-family: "Manrope", "Segoe UI", sans-serif;
            letter-spacing: -.03em;
        }

        .portal-brand-title {
            font-weight: 800;
            line-height: 1.05;
        }

        .portal-page-title {
            font-weight: 700;
        }

        .portal-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            border-radius: 999px;
            padding: .38rem .7rem;
            background: rgba(61, 55, 48, 0.05);
            color: var(--portal-text);
            text-decoration: none;
            border: 1px solid rgba(61, 55, 48, 0.08);
        }

        .portal-chip:hover { color: var(--portal-text); }

        .portal-user-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            border: 0;
            background: #0f172a;
            color: #fff;
            font-weight: 700;
        }

        .portal-user-trigger:hover {
            color: #fff;
            background: #111827;
        }

        .portal-content { padding: 1rem; }

        .portal-section-label {
            font-size: .7rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--portal-sidebar-muted);
            margin-bottom: .7rem;
            font-weight: 700;
        }

        .portal-stat-card {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            padding: .9rem 1rem;
        }

        .portal-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            background: var(--portal-primary-soft);
            color: var(--portal-primary);
        }

        .portal-list-item {
            padding: .8rem;
            border-radius: 10px;
            border: 1px solid var(--portal-border);
            background: rgba(255, 255, 255, 0.28);
        }

        .portal-chart-wrap {
            padding: .8rem;
            border-radius: 10px;
            border: 1px solid var(--portal-border);
            background: var(--portal-surface-strong);
        }

        .portal-dropdown {
            min-width: 340px;
            padding: .6rem;
        }

        .portal-account-menu { position: relative; }
        .portal-account-menu > summary { list-style: none; cursor: pointer; }
        .portal-account-menu > summary::-webkit-details-marker { display: none; }
        .portal-account-menu > summary:focus-visible { outline: 3px solid var(--portal-primary); outline-offset: 3px; }
        .portal-account-menu > .portal-user-menu { position: absolute; right: 0; top: calc(100% + 8px); width: min(300px, 85vw); color: var(--portal-text); }
        .portal-section-label { border-top: 1px solid var(--portal-sidebar-border); }
        .portal-user-menu {
            min-width: 280px;
        }

        .portal-user-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .5rem .55rem .85rem;
        }

        .portal-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #111827;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
        }

        .portal-theme-switcher {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: .45rem;
            padding: .5rem .55rem;
        }

        .portal-theme-option {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 42px;
            border-radius: 10px;
            border: 1px solid var(--portal-border);
            background: transparent;
            color: var(--portal-text);
        }

        .portal-theme-option.active {
            background: rgba(61, 55, 48, 0.08);
            border-color: rgba(61, 55, 48, 0.18);
        }

        body[data-theme="dark"] .portal-theme-option.active {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.14);
        }

        .portal-menu-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            width: 100%;
            padding: .75rem .7rem;
            color: var(--portal-text);
            text-decoration: none;
            border-radius: 10px;
        }

        .portal-menu-link:hover {
            background: rgba(61, 55, 48, 0.05);
            color: var(--portal-text);
        }

        .portal-notification-item {
            display: block;
            padding: .7rem .8rem;
            border-radius: 10px;
            color: var(--portal-text);
            text-decoration: none;
        }

        .portal-notification-item:hover {
            background: rgba(61, 55, 48, 0.05);
            color: var(--portal-text);
        }

        .portal-content .card,
        .portal-content .table,
        .portal-content .modal-content {
            border-radius: 14px;
        }

        .portal-empty {
            color: var(--portal-muted);
            border: 1px dashed var(--portal-border);
            border-radius: 10px;
            padding: .9rem;
            background: rgba(255, 255, 255, 0.24);
        }

        .portal-toast-stack {
            z-index: 1090;
            width: min(420px, calc(100vw - 1.5rem));
            pointer-events: none;
        }

        .portal-toast-stack .toast {
            pointer-events: auto;
        }

        .table thead th {
            color: var(--portal-muted);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }

        .table tbody tr:hover {
            background: rgba(61, 55, 48, 0.03);
        }

        .form-control,
        .form-select {
            min-height: 44px;
            border-radius: 10px;
            border-color: rgba(104, 89, 70, 0.16);
            background: rgba(255, 255, 255, 0.85);
        }

        .portal-readonly {
            background: rgba(61, 55, 48, 0.03);
        }

        .portal-secret-field {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            letter-spacing: 0;
        }

        .portal-tags-box {
            position: relative;
            border: 1px solid rgba(104, 89, 70, 0.16);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.85);
            overflow: visible;
        }

        .portal-tags-input {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            padding: .65rem .75rem;
            min-height: 44px;
        }

        .portal-tags-input::placeholder {
            color: var(--portal-muted);
        }

        .portal-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            padding: .65rem .75rem;
            border-top: 1px solid rgba(104, 89, 70, 0.12);
            min-height: 52px;
        }

        .portal-tags-suggestions {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 2px);
            z-index: 25;
            max-height: 260px;
            overflow-y: auto;
            background: var(--portal-surface);
            border: 1px solid rgba(104, 89, 70, 0.16);
            border-top: 0;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 18px 32px rgba(41, 35, 29, 0.12);
        }

        .portal-tags-suggestion {
            display: flex;
            align-items: center;
            gap: .55rem;
            width: 100%;
            padding: .6rem .75rem;
            border: 0;
            background: transparent;
            color: var(--portal-text);
            text-align: left;
        }

        .portal-tags-suggestion:hover,
        .portal-tags-suggestion.active {
            background: rgba(81, 105, 125, 0.08);
        }

        .portal-tags-empty {
            padding: .6rem .75rem;
            color: var(--portal-muted);
            font-size: .9rem;
        }

        .portal-tag {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            border-radius: 999px;
            padding: .4rem .7rem;
            background: rgba(81, 105, 125, 0.1);
            color: var(--portal-text);
            border: 1px solid rgba(81, 105, 125, 0.16);
            font-size: .9rem;
        }

        .portal-tag button {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            color: inherit;
        }

        .form-control-color {
            min-height: 44px;
            border-radius: 10px;
        }

        .btn {
            border-radius: 10px;
            font-weight: 500;
            letter-spacing: -.01em;
        }

        .btn-primary {
            background: var(--portal-primary);
            border-color: var(--portal-primary);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: #874f34;
            border-color: #874f34;
        }

        .btn-outline-secondary {
            color: var(--portal-text);
            border-color: rgba(61, 55, 48, 0.16);
        }

        .badge.text-bg-light {
            background: rgba(61, 55, 48, 0.06) !important;
            color: var(--portal-text) !important;
        }

        h1, h2, h3, h4, h5, h6,
        .fw-semibold,
        .fw-bold {
            letter-spacing: -.02em;
        }

        @media (max-width: 991.98px) {
            .portal-shell { flex-direction: column; }
            .portal-sidebar {
                width: 100%;
                border-right: 0;
                border-bottom: 1px solid var(--portal-sidebar-border);
                position: static;
                height: auto;
            }
            .portal-topbar { position: static; }
            .portal-content { padding: .75rem; }
            .portal-dropdown { min-width: min(92vw, 340px); }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/portal-ui.css') }}?v=20260914">
    <script src="{{ asset('js/portal-ui.js') }}?v=20260914" defer></script>
    @stack('styles')
</head>
<body class="portal-body" data-theme="light">
@php
    $pageTitle = trim($__env->yieldContent('page_title')) ?: __('portal.title');
    $pageSubtitle = $__env->hasSection('page_subtitle')
        ? trim($__env->yieldContent('page_subtitle'))
        : '';
        $notificationItems = auth()->user()->canPortal('activity.read') ? \App\Modules\Shared\Models\ActivityLog::with(['user', 'subject'])
        ->latest()
        ->limit(6)
        ->get()
        ->map(function ($activity) {
            return [
                'title' => $activity->actionLabel(),
                'subtitle' => trim(($activity->user?->displayName() ?? __('portal.system')) . ' | ' . $activity->created_at?->diffForHumans()),
            ];
        }) : collect();
@endphp
<div class="portal-shell d-flex">
    <button class="portal-nav-backdrop" type="button" aria-label="Закрити навігацію" tabindex="-1" hidden></button>
    <aside id="portal-navigation" class="portal-sidebar p-3 p-lg-4" tabindex="-1">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
            <a class="portal-brand" href="{{ auth()->user()->portalHome() }}">
                <div class="portal-brand-title fw-semibold fs-4 lh-sm">{{ __('portal.title') }}</div>
            </a>
        </div>

        <button class="portal-nav-close btn btn-outline-light" type="button" aria-label="Закрити меню"><i class="bi bi-x-lg"></i></button>
        <div class="mb-4">
            <nav class="nav flex-column mb-3">
                @canany(["dashboard.read","monitoring.read"])<div class="portal-section-label mt-3 pt-2">Огляд</div>@endcanany
                @can('dashboard.read')<a class="nav-link {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}" href="{{ route('portal.dashboard') }}"><i class="bi bi-grid me-2"></i>Дашборд</a>@endcan
                @can('monitoring.read')<a class="nav-link {{ request()->routeIs('portal.monitoring.*') ? 'active' : '' }}" href="{{ route('portal.monitoring.index') }}"><i class="bi bi-activity me-2"></i>Моніторинг</a>@endcan
                @canany(["sites.read","companies.read","statuses.read"])<div class="portal-section-label mt-3 pt-2">Проєкти</div>@endcanany
                @can('sites.read')<a class="nav-link {{ request()->routeIs('portal.sites.*') ? 'active' : '' }}" href="{{ route('portal.sites.index') }}"><i class="bi bi-globe2 me-2"></i>Сайти</a>@endcan
                @can('companies.read')<a class="nav-link {{ request()->routeIs('portal.companies.*') ? 'active' : '' }}" href="{{ route('portal.companies.index') }}"><i class="bi bi-buildings me-2"></i>Компанії</a>@endcan
                @can('statuses.read')<a class="nav-link {{ request()->routeIs('portal.statuses.*') ? 'active' : '' }}" href="{{ route('portal.statuses.index') }}"><i class="bi bi-tags me-2"></i>Статуси</a>@endcan
                @canany(["ftp.read","hosting.read","hosting-accounts.read"])<div class="portal-section-label mt-3 pt-2">Технічні доступи</div>@endcanany
                @can('ftp.read')<a class="nav-link {{ request()->routeIs('portal.ftp.*') ? 'active' : '' }}" href="{{ route('portal.ftp.index') }}"><i class="bi bi-hdd-network me-2"></i>FTP</a>@endcan
                @can('hosting.read')<a class="nav-link {{ request()->routeIs('portal.hosting.*') ? 'active' : '' }}" href="{{ route('portal.hosting.index') }}"><i class="bi bi-server me-2"></i>Хостинги</a>@endcan
                @can('hosting-accounts.read')<a class="nav-link {{ request()->routeIs('portal.hosting-accounts.*') ? 'active' : '' }}" href="{{ route('portal.hosting-accounts.index') }}"><i class="bi bi-diagram-3 me-2"></i>Хостинг-акаунти</a>@endcan

                @canany(["support.read"])<div class="portal-section-label mt-3 pt-2">Підтримка</div>@endcanany
                @can('support.read')<a class="nav-link {{ request()->routeIs('portal.support.index', 'portal.support.show') ? 'active' : '' }}" href="{{ route('portal.support.index') }}"><i class="bi bi-life-preserver me-2"></i>Тікети підтримки</a>@endcan
                @can('support.read')<a class="nav-link {{ request()->routeIs('portal.support.analytics') ? 'active' : '' }}" href="{{ route('portal.support.analytics') }}"><i class="bi bi-bar-chart-line me-2"></i>Аналітика підтримки</a>@endcan
                @can('support.read')<a class="nav-link {{ request()->routeIs('portal.support.clients.*') ? 'active' : '' }}" href="{{ route('portal.support.clients.index') }}"><i class="bi bi-people me-2"></i>Клієнти підтримки</a>@endcan
                @can('support.read')<a class="nav-link {{ request()->routeIs('portal.support.settings') ? 'active' : '' }}" href="{{ route('portal.support.settings') }}"><i class="bi bi-gear me-2"></i>Налаштування підтримки</a>@endcan
                @canany(["users.read","activity.read","trash.read"])<div class="portal-section-label mt-3 pt-2">Адміністрування</div>@endcanany
                @can('users.read')<a class="nav-link {{ request()->routeIs('portal.users.*') ? 'active' : '' }}" href="{{ route('portal.users.index') }}"><i class="bi bi-people me-2"></i>Користувачі</a>@endcan
                @can('activity.read')<a class="nav-link {{ request()->routeIs('portal.activity.*') ? 'active' : '' }}" href="{{ route('portal.activity.index') }}"><i class="bi bi-journal-text me-2"></i>Журнал дій</a>@endcan
                @can('trash.read')<a class="nav-link {{ request()->routeIs('portal.trash.*') ? 'active' : '' }}" href="{{ route('portal.trash.index') }}"><i class="bi bi-trash3 me-2"></i>Кошик</a>@endcan
                <div class="portal-section-label mt-3 pt-2">Особисте</div>
                <a class="nav-link {{ request()->routeIs('portal.profile.*') ? 'active' : '' }}" href="{{ route('portal.profile.edit') }}"><i class="bi bi-person-circle me-2"></i>Мій кабінет</a>
            </nav>
        </div>
    </aside>

    <div class="portal-main">
        <div class="portal-content">
            <div class="portal-topbar px-3 px-lg-4 py-2 mb-3">
                <button class="portal-nav-open btn btn-outline-secondary" type="button" aria-controls="portal-navigation" aria-expanded="false"><i class="bi bi-list"></i><span class="visually-hidden">Відкрити меню</span></button>
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div>
                        <div class="portal-page-title fw-semibold">{{ $pageTitle }}</div>
                        @if($pageSubtitle !== '')
                            <div class="small portal-soft">{{ $pageSubtitle }}</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="dropdown">
                            <button class="portal-chip border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <span>{{ $notificationItems->count() }}</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end portal-dropdown">
                                <div class="d-flex align-items-center justify-content-between px-2 pt-1 pb-2">
                                    <div>
                                        <div class="fw-semibold">{{ __('portal.notifications') }}</div>
                                        <div class="small portal-soft">{{ __('portal.notifications_hint') }}</div>
                                    </div>
                                    @if(auth()->user()?->isAdmin())
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.activity.index') }}">
                                            {{ __('portal.activity_log') }}
                                        </a>
                                    @endif
                                </div>
                                @forelse($notificationItems as $item)
                                    <div class="portal-notification-item">
                                        <div class="small fw-semibold">{{ $item['title'] }}</div>
                                        <div class="small portal-soft">{{ $item['subtitle'] }}</div>
                                    </div>
                                @empty
                                    <div class="portal-empty m-2">{{ __('portal.empty') }}</div>
                                @endforelse
                            </div>
                        </div>
                        <span class="portal-chip">
                            <i class="bi bi-clock-history"></i>
                            <span>{{ now()->format('d.m.Y H:i') }}</span>
                        </span>
                        <details class="portal-account-menu">
                            <summary class="portal-user-trigger" aria-label="Меню користувача">
                                {{ mb_strtoupper(mb_substr(auth()->user()->displayName(), 0, 1)) }}
                            </summary>
                            <div class="portal-dropdown portal-user-menu">
                                <div class="portal-user-row">
                                    <div class="portal-user-avatar">
                                        {{ mb_strtoupper(mb_substr(auth()->user()->displayName(), 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold">{{ auth()->user()->displayName() }}</div>
                                        <div class="small portal-soft">{{ auth()->user()->email }}</div>
                                    </div>
                                </div>
                                <div class="portal-theme-switcher">
                                    <button class="portal-theme-option active" type="button" data-theme-option="light">
                                        <i class="bi bi-sun"></i>
                                        <span>{{ __('portal.theme_light') }}</span>
                                    </button>
                                    <button class="portal-theme-option" type="button" data-theme-option="dark">
                                        <i class="bi bi-moon"></i>
                                        <span>{{ __('portal.theme_dark') }}</span>
                                    </button>
                                </div>
                                <div class="px-2 pt-2">
                                    <a class="portal-menu-link" href="{{ route('portal.profile.edit') }}">
                                        <i class="bi bi-person-circle"></i>
                                        <span>{{ __('portal.profile') }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="portal-menu-link border-0 bg-transparent text-start">
                                            <i class="bi bi-box-arrow-right"></i>
                                            <span>{{ __('portal.logout') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            @php
                $portalFlashMessages = collect([
                    ['type' => 'success', 'message' => session('success')],
                    ['type' => 'danger', 'message' => session('error')],
                    ['type' => 'warning', 'message' => session('warning')],
                    ['type' => 'info', 'message' => session('info')],
                ])->filter(fn ($item) => filled($item['message']))->values();

                if ($errors->any()) {
                    $portalFlashMessages->push([
                        'type' => 'danger',
                        'message' => $errors->first(),
                    ]);
                }
            @endphp

            @if($portalFlashMessages->isNotEmpty())
                <div class="portal-toast-stack position-fixed bottom-0 end-0 p-3">
                    @foreach($portalFlashMessages as $message)
                        @php $closeClass = in_array($message['type'], ['success', 'danger'], true) ? 'btn-close-white' : ''; @endphp
                        <div class="toast align-items-center border-0 shadow-sm text-bg-{{ $message['type'] }}"
                             role="status"
                             aria-live="polite"
                             aria-atomic="true"
                             data-bs-autohide="true"
                             data-bs-delay="5500">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <div class="fw-semibold small mb-1">{{ __('portal.' . $message['type']) }}</div>
                                    <div>{{ $message['message'] }}</div>
                                </div>
                                <button type="button" class="btn-close {{ $closeClass }} me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('portal.close') }}"></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-delete-modal-title>{{ __('portal.confirm_delete_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('portal.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="fw-semibold mb-2" data-delete-modal-subject></div>
                <div class="portal-soft" data-delete-modal-message>{{ __('portal.confirm_delete_message') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('portal.close') }}</button>
                <button type="button" class="btn btn-danger" data-delete-modal-confirm>{{ __('portal.confirm_delete_action') }}</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('click', function (event) {
    document.querySelectorAll('.portal-account-menu[open]').forEach(function (menu) {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.portal-account-menu[open]').forEach(function (menu) {
        menu.removeAttribute('open'); menu.querySelector('summary').focus();
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const body = document.body;
        const themeButtons = document.querySelectorAll('[data-theme-option]');
        const themes = ['light', 'dark'];
        const storedTheme = localStorage.getItem('portal-theme');
        const initialTheme = themes.includes(storedTheme) ? storedTheme : 'light';

        body.dataset.theme = initialTheme;

        const syncThemeButtons = () => {
            themeButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.themeOption === body.dataset.theme);
            });
        };

        syncThemeButtons();

        themeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const nextTheme = button.dataset.themeOption;
                body.dataset.theme = nextTheme;
                localStorage.setItem('portal-theme', nextTheme);
                syncThemeButtons();
            });
        });
    })();

    (function () {
        if (typeof bootstrap === 'undefined') {
            return;
        }

        document.querySelectorAll('.toast').forEach((toastElement) => {
            bootstrap.Toast.getOrCreateInstance(toastElement).show();
        });
    })();

    (function () {
        const toggleButtons = document.querySelectorAll('[data-secret-toggle]');
        const copyButtons = document.querySelectorAll('[data-secret-copy]');

        const copyText = async (value) => {
            if (!value) return;

            try {
                await navigator.clipboard.writeText(value);
            } catch (error) {
                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }
        };

        toggleButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.querySelector(button.dataset.secretToggle);

                if (!input) {
                    return;
                }

                const secretValue = input.dataset.secretValue;
                const secretMasked = input.dataset.secretMasked || '******';
                const isCustomMasked = typeof secretValue !== 'undefined';
                const isHidden = isCustomMasked ? input.value === secretMasked : input.type === 'password';

                if (isCustomMasked) {
                    input.type = 'text';
                    input.value = isHidden ? secretValue : secretMasked;
                } else {
                    input.type = isHidden ? 'text' : 'password';
                }

                const icon = button.querySelector('i');

                if (icon) {
                    icon.classList.toggle('bi-eye', isHidden);
                    icon.classList.toggle('bi-eye-slash', !isHidden);
                }
            });
        });

        copyButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.querySelector(button.dataset.secretCopy);

                if (!input) {
                    return;
                }

                const secretValue = input.dataset.secretValue;
                copyText(typeof secretValue !== 'undefined' ? secretValue : (input.value || ''));
            });
        });
    })();

    (function () {
        const modal = document.getElementById('deleteConfirmModal');
        if (!modal || typeof bootstrap === 'undefined') return;

        const title = modal.querySelector('[data-delete-modal-title]');
        const subject = modal.querySelector('[data-delete-modal-subject]');
        const message = modal.querySelector('[data-delete-modal-message]');
        const confirmButton = modal.querySelector('[data-delete-modal-confirm]');
        const instance = bootstrap.Modal.getOrCreateInstance(modal);
        const deleteForms = document.querySelectorAll('form[data-delete-confirm]');
        let pendingForm = null;

        const resetState = () => {
            pendingForm = null;
        };

        deleteForms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.deleteConfirmed === 'true') {
                    delete form.dataset.deleteConfirmed;
                    return;
                }

                event.preventDefault();
                pendingForm = form;

                if (title) {
                    title.textContent = form.dataset.deleteTitle || '{{ __('portal.confirm_delete_title') }}';
                }

                if (subject) {
                    subject.textContent = form.dataset.deleteSubject || '';
                    subject.classList.toggle('d-none', !subject.textContent);
                }

                if (message) {
                    message.textContent = form.dataset.deleteMessage || '{{ __('portal.confirm_delete_message') }}';
                }

                if (confirmButton) {
                    confirmButton.textContent = form.dataset.deleteAction || '{{ __('portal.confirm_delete_action') }}';
                }

                instance.show();
            });
        });

        confirmButton?.addEventListener('click', () => {
            if (!pendingForm) return;

            const form = pendingForm;
            form.dataset.deleteConfirmed = 'true';
            resetState();
            instance.hide();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        });

        modal.addEventListener('hidden.bs.modal', resetState);
    })();

    (function () {
        const roots = document.querySelectorAll('[data-tags-input]');

            const normalize = (value) => String(value ?? '').trim().toLowerCase();
            const optionText = (option) => normalize(`${option.label ?? ''} ${option.search ?? ''} ${option.id ?? ''}`);

        roots.forEach((root) => {
            const input = root.querySelector('[data-tags-input-field]');
            const list = root.querySelector('[data-tags-list]');
            const suggestions = root.querySelector('[data-tags-suggestions]');
            const hidden = root.querySelector('[data-tags-hidden]');
            if (!input || !list || !hidden || !suggestions) {
                return;
            }

            let options = [];
            let selected = [];

            try {
                options = JSON.parse(root.dataset.tagsOptions || '[]');
            } catch (error) {
                options = [];
            }

            try {
                selected = JSON.parse(root.dataset.tagsSelected || '[]');
            } catch (error) {
                selected = [];
            }

            const name = root.dataset.tagsName || 'values';
            const placeholder = root.dataset.tagsPlaceholder || '';
            let activeIndex = -1;

            const getFilteredOptions = () => {
                const query = normalize(input.value);

                if (query.length < 2) {
                    return [];
                }

                return options.filter((option) => {
                    if (selected.some((id) => String(id) === String(option.id))) {
                        return false;
                    }

                    if (!query) {
                        return true;
                    }

                    return optionText(option).includes(query);
                });
            };

            const hideSuggestions = () => {
                suggestions.innerHTML = '';
                suggestions.classList.add('d-none');
                activeIndex = -1;
            };

            const showSuggestions = () => {
                const filtered = getFilteredOptions();
                suggestions.innerHTML = '';

                if (!filtered.length) {
                    if (normalize(input.value).length >= 2) {
                        const empty = document.createElement('div');
                        empty.className = 'portal-tags-empty';
                        empty.textContent = '{{ __('portal.empty') }}';
                        suggestions.appendChild(empty);
                        suggestions.classList.remove('d-none');
                        return;
                    }

                    hideSuggestions();
                    return;
                }

                filtered.slice(0, 8).forEach((option, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'portal-tags-suggestion';
                    if (index === activeIndex) {
                        button.classList.add('active');
                    }
                    button.dataset.tagId = option.id;
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-search';

                    const text = document.createElement('div');
                    text.className = 'd-flex flex-column min-w-0';

                    const title = document.createElement('div');
                    title.className = 'fw-semibold text-truncate';
                    title.textContent = option.label;
                    text.appendChild(title);

                    if (option.search) {
                        const subtitle = document.createElement('div');
                        subtitle.className = 'small text-body-secondary text-truncate';
                        subtitle.textContent = option.search;
                        text.appendChild(subtitle);
                    }

                    button.appendChild(icon);
                    button.appendChild(text);
                    button.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        selected = [...selected, option.id];
                        input.value = '';
                        render();
                        input.focus();
                    });
                    suggestions.appendChild(button);
                });

                suggestions.classList.remove('d-none');
            };

            const render = () => {
                list.innerHTML = '';
                hidden.innerHTML = '';

                selected.forEach((id) => {
                    const option = options.find((item) => String(item.id) === String(id));
                    if (!option) {
                        return;
                    }

                    const chip = document.createElement('span');
                    chip.className = 'portal-tag';

                    const label = document.createElement('span');
                    label.className = 'text-truncate';
                    label.textContent = option.label;

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.innerHTML = '&times;';
                    remove.setAttribute('aria-label', 'Remove');
                    remove.addEventListener('click', (event) => {
                        event.stopPropagation();
                        selected = selected.filter((current) => String(current) !== String(option.id));
                        render();
                    });

                    chip.appendChild(label);
                    chip.appendChild(remove);
                    list.appendChild(chip);

                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `${name}[]`;
                    hiddenInput.value = option.id;
                    hidden.appendChild(hiddenInput);
                });

                input.placeholder = placeholder;
                showSuggestions();
            };

            const addValue = (rawValue) => {
                const option = options.find((item) => String(item.id) === String(rawValue));
                if (!option || selected.some((id) => String(id) === String(option.id))) {
                    return;
                }

                selected = [...selected, option.id];
                input.value = '';
                hideSuggestions();
                render();
            };

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    const filtered = getFilteredOptions();
                    if (filtered.length === 1) {
                        addValue(filtered[0].id);
                    }
                    return;
                }

                if (event.key === 'ArrowDown') {
                    const filtered = getFilteredOptions();
                    if (!filtered.length) return;
                    event.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, filtered.length - 1);
                    showSuggestions();
                    return;
                }

                if (event.key === 'ArrowUp') {
                    const filtered = getFilteredOptions();
                    if (!filtered.length) return;
                    event.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    showSuggestions();
                    return;
                }

                if (event.key === 'Escape') {
                    hideSuggestions();
                }
            });

            input.addEventListener('input', () => {
                activeIndex = -1;
                showSuggestions();
            });

            input.addEventListener('focus', () => {
                showSuggestions();
            });

            input.addEventListener('blur', () => {
                window.setTimeout(() => hideSuggestions(), 120);
            });

            root.addEventListener('click', () => input.focus());
            render();
        });
    })();
</script>
@stack('scripts')
</body>
</html>


