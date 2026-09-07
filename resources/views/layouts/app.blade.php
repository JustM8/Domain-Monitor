<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', __('portal.title')) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(99, 102, 241, .22), transparent 32%),
                radial-gradient(circle at top right, rgba(14, 165, 233, .18), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }

        .guest-shell {
            min-height: 100vh;
        }

        .guest-topbar {
            padding: 1.25rem 0;
        }

        .guest-brand {
            font-weight: 700;
            letter-spacing: -.02em;
            color: #0f172a;
            text-decoration: none;
        }

        .guest-card {
            background: rgba(255, 255, 255, .86);
            border: 1px solid rgba(148, 163, 184, .18);
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
            border-radius: 24px;
        }

        .guest-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .8rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, .1);
            color: #1d4ed8;
            font-size: .92rem;
            font-weight: 600;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="guest-shell">
    <div class="container">
        <div class="guest-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <a class="guest-brand fs-4" href="{{ url('/') }}">{{ __('portal.title') }}</a>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @auth
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('portal.dashboard') }}">
                        <i class="bi bi-speedometer2 me-1"></i>{{ __('portal.dashboard') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-dark rounded-pill">
                            <i class="bi bi-box-arrow-right me-1"></i>{{ __('portal.logout') }}
                        </button>
                    </form>
                @else
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('login') }}">
                        <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('portal.login') }}
                    </a>
                    @if(Route::has('register'))
                        <a class="btn btn-primary rounded-pill" href="{{ route('register') }}">
                            <i class="bi bi-person-plus me-1"></i>Реєстрація
                        </a>
                    @endif
                @endauth
            </div>
        </div>
        @yield('content')
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
