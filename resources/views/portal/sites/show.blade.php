@extends('layouts.portal')

@section('content')
@php
    $remoteControlEnabled = (bool) $site->remote_control_enabled;
    $syncStatus = $site->last_sync_status ?? 'unknown';
    $syncBadge = match ($syncStatus) {
        'active' => 'success',
        'disabled' => 'warning',
        'failed' => 'danger',
        default => 'secondary',
    };
@endphp
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h1 class="h4 mb-0">{{ $site->name }}</h1>
                <span class="badge rounded-pill text-bg-{{ $site->is_active ? 'success' : 'danger' }}">
                    {{ $site->is_active ? __('portal.active') : __('portal.disabled') }}
                </span>
                @if($site->status)
                    <span class="badge rounded-pill" style="background-color: {{ $site->status->color }}; color: #fff;">
                        {{ __($site->status->name) }}
                    </span>
                @endif
            </div>
            <div class="portal-soft">{{ $site->url }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ $site->url }}">
                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('portal.open') }}
            </a>
            <form method="POST" action="{{ route('portal.sites.check', $site) }}">
                @csrf
                <button class="btn btn-outline-primary">
                    <i class="bi bi-shield-check me-1"></i>200
                </button>
            </form>
            <form method="POST" action="{{ route('portal.sites.sync', $site) }}">
                @csrf
                <button class="btn btn-outline-primary" @disabled(! $remoteControlEnabled) title="{{ $remoteControlEnabled ? '' : __('portal.remote_control_disabled_notice') }}">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('portal.sync_now') }}
                </button>
            </form>
            <button class="btn btn-outline-primary" type="button" data-edit-toggle>
                <i class="bi bi-pencil-square me-1"></i>{{ __('portal.edit') }}
            </button>
            @if(auth()->user()->isAdmin() || auth()->user()->isPm())
                <form method="POST" action="{{ route('portal.sites.remote-control', $site) }}">
                    @csrf
                    <button class="btn {{ $remoteControlEnabled ? 'btn-outline-danger' : 'btn-outline-success' }}">
                        <i class="bi bi-toggle-{{ $remoteControlEnabled ? 'on' : 'off' }} me-1"></i>
                        {{ $remoteControlEnabled ? __('portal.remote_control_disable') : __('portal.remote_control_enable') }}
                    </button>
                </form>
            @endif
            @if($site->is_active)
                <form method="POST" action="{{ route('portal.sites.disable', $site) }}">
                    @csrf
                    <input type="hidden" name="disabled_reason" value="{{ __('portal.disabled_default_reason') }}">
                    <button class="btn btn-warning">{{ __('portal.disable') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('portal.sites.enable', $site) }}">
                    @csrf
                    <button class="btn btn-success">{{ __('portal.enable') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('portal.sites.destroy', $site) }}" data-delete-confirm data-delete-subject="{{ $site->name }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger">{{ __('portal.delete') }}</button>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <h2 class="h5 mb-3">{{ __('portal.quick_info') }}</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.company') }}</div>
                    <div class="fw-semibold">{{ $site->company?->name ?? __('portal.empty') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.status') }}</div>
                    <div class="fw-semibold">
                        @if($site->status)
                            <span class="badge rounded-pill" style="background-color: {{ $site->status->color }}; color: #fff;">{{ __($site->status->name) }}</span>
                        @else
                            {{ __('portal.empty') }}
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.site_product_type') }}</div>
                    <div class="fw-semibold">{{ $site->siteTypeLabel() }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.environment') }}</div>
                    <div class="fw-semibold">{{ $site->environmentLabel() }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.cms') }}</div>
                    <div class="fw-semibold">{{ $site->cms ?? __('portal.empty') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.version') }}</div>
                    <div class="fw-semibold">{{ $site->version ?? __('portal.empty') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.repo_url') }}</div>
                    <div class="fw-semibold text-break">{{ $site->repo_url ?? __('portal.empty') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="portal-soft small">{{ __('portal.branch') }}</div>
                    <div class="fw-semibold">{{ $site->branch ?? __('portal.empty') }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <h2 class="h5 mb-3">{{ __('portal.access_block') }}</h2>
            @if(auth()->user()->isAdmin() || auth()->user()->isPm())
                <div class="mb-3">
                    <div class="portal-soft small">{{ __('portal.api_token') }}</div>
                    <div class="input-group">
                        <input id="site-api-token" type="password" class="form-control portal-readonly portal-secret-field" readonly value="{{ $apiToken }}">
                        <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#site-api-token">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-secret-copy="#site-api-token">
                            <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                        </button>
                    </div>
                </div>
            @endif
            <div class="mb-3">
                <div class="portal-soft small">{{ __('portal.admin_url') }}</div>
                <div class="fw-semibold">{{ $site->admin_url ?? __('portal.empty') }}</div>
            </div>
                <div class="mb-3">
                    <div class="portal-soft small">{{ __('portal.admin_login') }}</div>
                    <div class="fw-semibold">{{ $site->admin_login ?? __('portal.empty') }}</div>
                </div>
            <div>
                <div class="portal-soft small">{{ __('portal.admin_password') }}</div>
                @if($adminPassword)
                    <div class="input-group">
                        <input id="site-admin-password" type="password" class="form-control portal-readonly portal-secret-field" readonly value="{{ $adminPassword }}">
                        <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#site-admin-password">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-secret-copy="#site-admin-password">
                            <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                        </button>
                    </div>
                @else
                    <div class="fw-semibold">{{ __('portal.empty') }}</div>
                @endif
            </div>
            <div class="mt-3">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
                    <div class="portal-soft small">{{ __('portal.remote_control') }}</div>
                    <span class="badge text-bg-{{ $remoteControlEnabled ? 'success' : 'secondary' }}">
                        {{ $remoteControlEnabled ? __('portal.remote_control_enabled') : __('portal.remote_control_disabled') }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($remoteControlEnabled)
                        <span class="badge text-bg-{{ $syncBadge }}">
                            {{ __('portal.sync_status_' . $syncStatus) }}
                        </span>
                        <span class="small portal-soft">
                            {{ $site->last_synced_at ? $site->last_synced_at->diffForHumans() : __('portal.never') }}
                        </span>
                    @else
                        <span class="small text-muted">{{ __('portal.remote_control_hint') }}</span>
                    @endif
                </div>
                @if($remoteControlEnabled && $site->last_sync_status === 'failed' && $site->last_sync_error)
                    <details class="mt-2">
                        <summary class="small text-danger">{{ __('portal.remote_control_failed') }}</summary>
                        <div class="border rounded p-3 bg-light mt-2">
                            <div class="fw-semibold text-danger mb-2">{{ __('portal.remote_control_failed') }}</div>
                            <div class="small text-body text-break" style="max-height: 180px; overflow: auto; white-space: pre-wrap;">{{ $site->last_sync_error }}</div>
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="portal-card p-4 mb-3 d-none" data-edit-panel>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.edit_mode') }}</h2>
            <div class="portal-soft small">{{ __('portal.read_only_view') }}</div>
        </div>
        <button class="btn btn-outline-secondary" type="button" data-edit-toggle>
            <i class="bi bi-x-circle me-1"></i>{{ __('portal.read_only_view') }}
        </button>
    </div>

    <form method="POST" action="{{ route('portal.sites.update', $site) }}" class="row g-3">
        @csrf
        @method('PUT')
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.site_name') }}</label>
            <input name="name" class="form-control" value="{{ $site->name }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.site_url') }}</label>
            <input name="url" class="form-control" value="{{ $site->url }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.site_product_type') }}</label>
            <select name="site_type" class="form-select">
                @foreach($siteTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($site->site_type === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.environment') }}</label>
            <select name="environment" class="form-select">
                @foreach($environmentOptions as $value => $label)
                    <option value="{{ $value }}" @selected($site->environment === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="remote_control_enabled" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="site-remote-control-edit" name="remote_control_enabled" value="1" @checked($remoteControlEnabled)>
                <label class="form-check-label" for="site-remote-control-edit">{{ __('portal.remote_control') }}</label>
            </div>
            <div class="portal-soft small">{{ __('portal.remote_control_hint') }}</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.admin_url') }}</label>
            <input name="admin_url" class="form-control" value="{{ $site->admin_url }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.admin_login') }}</label>
            <input name="admin_login" class="form-control" value="{{ $site->admin_login }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.admin_password') }}</label>
            <input name="admin_password" type="password" class="form-control" value="{{ old('admin_password') }}" autocomplete="new-password" spellcheck="false" placeholder="{{ __('portal.edit') }}">
            <div class="portal-soft small mt-1">{{ __('portal.admin_password_hint') }}</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.status') }}</label>
            <select name="status_id" class="form-select">
                <option value="">{{ __('portal.status') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected($site->status_id === $status->id)>{{ __($status->name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.company') }}</label>
            <select name="company_id" class="form-select">
                <option value="">{{ __('portal.company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected($site->company_id === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.repo_url') }}</label>
            <input name="repo_url" class="form-control" value="{{ $site->repo_url }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.branch') }}</label>
            <input name="branch" class="form-control" value="{{ $site->branch }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.cms') }}</label>
            <input name="cms" class="form-control" value="{{ $site->cms }}" placeholder="{{ __('portal.cms_hint') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('portal.version') }}</label>
            <input name="version" class="form-control" value="{{ $site->version }}">
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('portal.note') }}</label>
            <textarea name="note" class="form-control" rows="3">{{ $site->note }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
            <button class="btn btn-outline-secondary" type="button" data-edit-toggle>
                <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('portal.read_only_view') }}
            </button>
        </div>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <h2 class="h5">{{ __('portal.ftp') }}</h2>
            <ul class="list-group list-group-flush">
                @forelse($site->ftpAccounts as $ftp)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <div>
                                <div class="fw-semibold">{{ $ftp->host }}:{{ $ftp->port }}</div>
                                <div class="portal-soft small">{{ $ftp->login ?? __('portal.empty') }}</div>
                            </div>
                            <div class="text-end">
                                <div class="small portal-soft">
                                    {{ $ftp->company?->name ?? __('portal.empty') }}
                                </div>
                                @if($ftp->requires_ip_access)
                                    <span class="badge text-bg-warning">{{ __('portal.requires_ip_access') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 small">
                            <span class="portal-soft">{{ __('portal.linked_sites') }}:</span>
                            @forelse($ftp->sites as $linkedSite)
                                <span class="badge text-bg-light border">{{ $linkedSite->name }}</span>
                            @empty
                                <span class="portal-soft">{{ __('portal.empty') }}</span>
                            @endforelse
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <div class="small text-break">
                                <span class="portal-soft">{{ __('portal.path') }}:</span> {{ $ftp->path ?? __('portal.empty') }}
                            </div>
                            <div class="input-group input-group-sm" style="max-width: 320px;">
                                <input id="ftp-password-{{ $ftp->id }}" type="password" class="form-control portal-readonly portal-secret-field" readonly value="{{ $ftp->decryptedPassword() ?? '' }}">
                                <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#ftp-password-{{ $ftp->id }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" data-secret-copy="#ftp-password-{{ $ftp->id }}">
                                    <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                                </button>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item portal-soft">{{ __('portal.empty') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <h2 class="h5">{{ __('portal.hosting') }}</h2>
            <ul class="list-group list-group-flush">
                @forelse($site->hostingAccounts as $hosting)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <div>
                                <div class="fw-semibold">{{ $hosting->title }}</div>
                                <div class="portal-soft small">{{ $hosting->hosting?->name ?? __('portal.empty') }}</div>
                            </div>
                            <div class="text-end portal-soft small">
                                {{ $hosting->company?->name ?? __('portal.empty') }}
                            </div>
                        </div>
                        <div class="mt-2 small">
                            <span class="portal-soft">{{ __('portal.linked_sites') }}:</span>
                            @forelse($hosting->sites as $linkedSite)
                                <span class="badge text-bg-light border">{{ $linkedSite->name }}</span>
                            @empty
                                <span class="portal-soft">{{ __('portal.empty') }}</span>
                            @endforelse
                        </div>
                    </li>
                @empty
                    <li class="list-group-item portal-soft">{{ __('portal.empty') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="portal-card p-4 mt-3">
    <h2 class="h5 mb-3">{{ __('portal.revisions') }}</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.when') }}</th>
                <th>{{ __('portal.by_whom') }}</th>
                <th>{{ __('portal.change_type') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($site->revisions as $revision)
                <tr>
                    <td>{{ $revision->created_at }}</td>
                    <td>{{ $revision->changedBy?->displayName() ?? __('portal.empty') }}</td>
                    <td>{{ $revision->change_type }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="portal-soft">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const editPanel = document.querySelector('[data-edit-panel]');
        const buttons = document.querySelectorAll('[data-edit-toggle]');

        if (!editPanel || !buttons.length) {
            return;
        }

        const sync = (open) => {
            editPanel.classList.toggle('d-none', !open);
            buttons.forEach((button) => {
                button.dataset.state = open ? 'open' : 'closed';
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                sync(editPanel.classList.contains('d-none'));
                if (!editPanel.classList.contains('d-none')) {
                    editPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    })();
</script>
@endpush
@endsection
