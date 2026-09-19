@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.sites_list') }}</h2>
            <div class="portal-soft small">{{ __('portal.sites_list_hint') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('sites.write')<a class="btn btn-primary" href="{{ route('portal.sites.add') }}">
                <i class="bi bi-plus-circle me-1"></i>{{ __('portal.add_site') }}
            </a>@endcan
            @can('trash.read')<a class="btn btn-outline-secondary" href="{{ route('portal.trash.index') }}">
                <i class="bi bi-trash3 me-1"></i>{{ __('portal.trash') }}
            </a>@endcan
        </div>
    </div>
</div>

<div class="portal-card p-4 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.search') }}</label>
            <input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('portal.search') }}">
        </div>
        <div class="col-lg-2">
            <label class="form-label">{{ __('portal.status') }}</label>
            <select name="status_id" class="form-select">
                <option value="">{{ __('portal.status') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected(request('status_id') == $status->id)>{{ __($status->name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">{{ __('portal.company') }}</label>
            <select name="company_id" class="form-select">
                <option value="">{{ __('portal.company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">{{ __('portal.site_product_type') }}</label>
            <select name="site_type" class="form-select">
                <option value="">{{ __('portal.site_product_type') }}</option>
                @foreach($siteTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('site_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">{{ __('portal.environment') }}</label>
            <select name="environment" class="form-select">
                <option value="">{{ __('portal.environment') }}</option>
                @foreach($environmentOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('environment') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2"><label class="form-label">Відображення</label><select name="display_mode" class="form-select"><option value="">Усі</option><option value="standalone" @selected(request('display_mode') === 'standalone')>Окремий сайт</option><option value="iframe" @selected(request('display_mode') === 'iframe')>iframe</option></select></div>
        <div class="col-12">
            <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
        </div>
    </form>
</div>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.site_name') }}</th>
                <th>{{ __('portal.site_product_type') }}</th>
                <th>{{ __('portal.environment') }}</th>
                <th>{{ __('portal.status') }}</th>
                <th>{{ __('portal.company') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($sites as $site)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $site->name }}</div>
                        <div class="portal-soft small text-truncate" style="max-width: 260px;">{{ $site->url }}</div>
                    </td>
                    <td>{{ $site->siteTypeLabel() }} @if($site->display_mode === 'iframe')<span class="badge text-bg-light">iframe</span>@endif</td>
                    <td>{{ $site->environmentLabel() }}</td>
                    <td>
                        <div class="d-flex flex-column gap-2">
                            @if($site->status)
                                <span class="badge rounded-pill align-self-start" style="background-color: {{ $site->status->color }}; color: #fff;">
                                    {{ __($site->status->name) }}
                                </span>
                            @endif
                            <span class="badge rounded-pill text-bg-{{ $site->is_active ? 'success' : 'danger' }} align-self-start">
                                {{ $site->is_active ? __('portal.active') : __('portal.disabled') }}
                            </span>
                        </div>
                    </td>
                    <td>{{ $site->company?->name ?? __('portal.empty') }}</td>
                    <td>
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ $site->url }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('portal.open') }}
                            </a>
                            @can('sites.read')<form method="POST" action="{{ route('portal.sites.check', $site) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-shield-check me-1"></i>200
                                </button>
                            </form>@endcan
                            @can('sites.read')<a class="btn btn-sm btn-primary" href="{{ route('portal.sites.show', $site) }}">{{ __('portal.details') }}</a>@endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="portal-soft">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $sites->links() }}
</div>
@endsection
