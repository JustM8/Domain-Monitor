@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.ftp_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.ftp_list') }}</div>
        </div>
        @can('ftp.read')<a class="btn btn-outline-secondary" href="{{ route('portal.ftp.index') }}">{{ __('portal.ftp_list') }}</a>@endcan
    </div>
    @can('ftp.write')<form method="POST" action="{{ route('portal.ftp.store') }}" class="row g-3">
        @csrf
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.company') }}</label>
            <select name="company_id" class="form-select">
                <option value="">{{ __('portal.company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-8">
            <label class="form-label">{{ __('portal.linked_sites') }}</label>
            <div class="portal-tags-box" data-tags-input data-tags-name="site_ids" data-tags-placeholder="{{ __('portal.new_tag') }}" data-tags-options='@json($sites->map(fn ($site) => ["id" => $site->id, "label" => $site->name, "search" => trim(($site->url ?? "") . " " . ($site->siteTypeLabel() ?? "") . " " . ($site->environmentLabel() ?? ""))])->values())' data-tags-selected='@json(old("site_ids", []))'>
                <input type="text" class="portal-tags-input" data-tags-input-field placeholder="{{ __('portal.new_tag') }}">
                <div class="portal-tags-suggestions d-none" data-tags-suggestions></div>
                <div class="portal-tags-list" data-tags-list></div>
                <div data-tags-hidden></div>
            </div>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.host') }}</label>
            <input name="host" class="form-control" value="{{ old('host') }}" required>
        </div>
        <div class="col-lg-4">
            <label class="form-label">Port</label>
            <input name="port" class="form-control" type="number" value="{{ old('port', 21) }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.login_label') }}</label>
            <input name="login" class="form-control" value="{{ old('login') }}">
        </div>
        <div class="col-lg-6">
            <label class="form-label">{{ __('portal.password') }}</label>
            <input type="password" name="password" class="form-control" value="{{ old('password') }}" autocomplete="off" spellcheck="false">
        </div>
        <div class="col-lg-6">
            <label class="form-label">{{ __('portal.path') }}</label>
            <input name="path" class="form-control" value="{{ old('path') }}">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input id="ftp-ip-access-add" class="form-check-input" type="checkbox" name="requires_ip_access" value="1" @checked(old('requires_ip_access'))>
                <label class="form-check-label" for="ftp-ip-access-add">{{ __('portal.requires_ip_access') }}</label>
            </div>
        </div>
        <div class="col-12">
            <details class="portal-surface p-3">
                <summary class="fw-semibold">{{ __('portal.additional_fields') }}</summary>
                <div class="mt-3">
                    <label class="form-label">{{ __('portal.note') }}</label>
                    <textarea name="note" class="form-control" rows="4">{{ old('note') }}</textarea>
                </div>
            </details>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
        </div>
    </form>@endcan
</div>
@endsection
