@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.site_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.site_create_hint') }}</div>
        </div>
        @can('sites.read')<a class="btn btn-outline-secondary" href="{{ route('portal.sites.index') }}">
            <i class="bi bi-list me-1"></i>{{ __('portal.sites_list') }}
        </a>@endcan
    </div>

    @can('sites.write')<form method="POST" action="{{ route('portal.sites.store') }}" class="row g-3">
        @csrf
        <div class="col-lg-6">
            <label class="form-label">{{ __('portal.site_name') }}</label>
            <input name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="col-lg-6">
            <label class="form-label">{{ __('portal.site_url') }}</label>
            <input name="url" class="form-control" value="{{ old('url') }}" required>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.admin_login') }}</label>
            <input name="admin_login" class="form-control" value="{{ old('admin_login') }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.admin_password') }}</label>
            <input type="password" name="admin_password" class="form-control" value="{{ old('admin_password') }}" autocomplete="new-password" spellcheck="false">
            <div class="portal-soft small mt-1">{{ __('portal.admin_password_hint') }}</div>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.company') }}</label>
            <select name="company_id" class="form-select">
                <option value="">{{ __('portal.company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.status') }}</label>
            <select name="status_id" class="form-select">
                <option value="">{{ __('portal.status') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected(old('status_id') == $status->id)>{{ __($status->name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.site_product_type') }}</label>
            <select name="site_type" class="form-select">
                @foreach($siteTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('site_type', 'site') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.environment') }}</label>
            <select name="environment" class="form-select">
                @foreach($environmentOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('environment', 'dev') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @include('portal.sites.operation-mode')
        @include('portal.monitoring.options', ['monitoringReadOnly' => ! auth()->user()->canPortal('sites.write')])
        @include('portal.sites.presentation-fields')

        <div class="col-12">
            <details class="portal-surface p-3">
                <summary class="fw-semibold">{{ __('portal.additional_fields') }}</summary>
                <div class="row g-3 mt-2">
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('portal.admin_url') }}</label>
                        <input name="admin_url" class="form-control" value="{{ old('admin_url') }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('portal.repo_url') }}</label>
                        <input name="repo_url" class="form-control" value="{{ old('repo_url') }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('portal.branch') }}</label>
                        <input name="branch" class="form-control" value="{{ old('branch') }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('portal.cms') }}</label>
                        <input name="cms" class="form-control" value="{{ old('cms') }}" placeholder="{{ __('portal.cms_hint') }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('portal.version') }}</label>
                        <input name="version" class="form-control" value="{{ old('version') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('portal.note') }}</label>
                        <textarea name="note" class="form-control" rows="4">{{ old('note') }}</textarea>
                    </div>
                </div>
            </details>
        </div>

        <div class="col-12">
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
        </div>
    </form>@endcan
</div>
@endsection
