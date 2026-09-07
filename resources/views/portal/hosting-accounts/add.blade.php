@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.hosting_accounts') }}</h2>
            <div class="portal-soft small">{{ __('portal.hosting_accounts') }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('portal.hosting-accounts.index') }}">{{ __('portal.hosting_accounts') }}</a>
    </div>
    <form method="POST" action="{{ route('portal.hosting-accounts.store') }}" class="row g-3">
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
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.hosting') }}</label>
            <select name="hosting_id" class="form-select" required>
                @foreach($hostings as $hosting)
                    <option value="{{ $hosting->id }}" @selected(old('hosting_id') == $hosting->id)>{{ $hosting->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.linked_sites') }}</label>
            <div class="portal-tags-box" data-tags-input data-tags-name="site_ids" data-tags-placeholder="{{ __('portal.new_tag') }}" data-tags-options='@json($sites->map(fn ($site) => ["id" => $site->id, "label" => $site->name, "search" => trim(($site->url ?? "") . " " . ($site->siteTypeLabel() ?? "") . " " . ($site->environmentLabel() ?? ""))])->values())' data-tags-selected='@json(old("site_ids", []))'>
                <input type="text" class="portal-tags-input" data-tags-input-field placeholder="{{ __('portal.new_tag') }}">
                <div class="portal-tags-suggestions d-none" data-tags-suggestions></div>
                <div class="portal-tags-list" data-tags-list></div>
                <div data-tags-hidden></div>
            </div>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.name') }}</label>
            <input name="title" class="form-control" value="{{ old('title') }}" required>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.login_label') }}</label>
            <input name="login" class="form-control" value="{{ old('login') }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.password') }}</label>
            <input name="password" type="text" class="form-control" value="{{ old('password') }}" autocomplete="off" spellcheck="false">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.ssh_host') }}</label>
            <input name="ssh_host" class="form-control" value="{{ old('ssh_host') }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.ssh_port') }}</label>
            <input name="ssh_port" class="form-control" type="number" value="{{ old('ssh_port') }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('portal.ssh_login') }}</label>
            <input name="ssh_login" class="form-control" value="{{ old('ssh_login') }}">
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('portal.ssh_password') }}</label>
            <input name="ssh_password" type="text" class="form-control" value="{{ old('ssh_password') }}" autocomplete="off" spellcheck="false">
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
    </form>
</div>
@endsection
