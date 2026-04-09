<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Domain Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table tr:hover {
            background: #f8f9fa;
            transition: 0.2s;
        }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">

        <a class="navbar-brand" href="{{ route('domains.index') }}">Monitor</a>
        <div class="collapse navbar-collapse">
            {{-- MENU --}}
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('domains*') ? 'active' : '' }}"
                       href="{{ route('domains.index') }}">
                        Domains
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('checks*') ? 'active' : '' }}"
                       href="{{ route('checks.index') }}">
                        Checks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('settings*') ? 'active' : '' }}"
                       href="{{ route('settings.index') }}">
                        Settings
                    </a>
                </li>
            </ul>

            {{-- USER --}}
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-light">Logout</button>
                </form>
            </div>

        </div>
    </div>
</nav>

<div class="container">
    {{-- SUCCESS --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- ERROR --}}
    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
<script>
    function handleCheck(form) {
        const btn = form.querySelector('button');
        btn.disabled = true;

        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.spinner-border').classList.remove('d-none');
    }
</script>
</body>
</html>
