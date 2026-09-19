@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <h2 class="h5 mb-0">{{ __('portal.edit') }}: {{ $account->host }}</h2>
                <span class="badge text-bg-light">{{ __('portal.read_only_view') }}</span>
            </div>
            <div class="vstack gap-3">
                <div>
                    <div class="portal-soft small">{{ __('portal.company') }}</div>
                    <div class="fw-semibold">{{ $account->company?->name ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.linked_sites') }}</div>
                    <div class="d-flex flex-wrap gap-1">
                        @forelse($account->sites as $site)
                            <span class="badge text-bg-light border">{{ $site->name }}</span>
                        @empty
                            <span class="fw-semibold">{{ $account->site?->name ?? __('portal.empty') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.host') }}</div>
                        <div class="fw-semibold">{{ $account->host }}:{{ $account->port }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.login_label') }}</div>
                        <div class="fw-semibold">{{ $account->login ?? __('portal.empty') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.path') }}</div>
                        <div class="fw-semibold">{{ $account->path ?? __('portal.empty') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.requires_ip_access') }}</div>
                        <div class="fw-semibold">
                            {{ $account->requires_ip_access ? __('portal.yes') : __('portal.no') }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.password') }}</div>
                    @if($passwordValue)
                        <div class="input-group">
                            <input
                                id="ftp-password-view"
                                type="text"
                                class="form-control portal-readonly portal-secret-field"
                                readonly
                                value="******"
                                data-secret-value="{{ $passwordValue }}"
                                data-secret-masked="******"
                            >
                            <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#ftp-password-view">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-secret-copy="#ftp-password-view">
                                <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                            </button>
                            @can('ftp.read')<a class="btn btn-outline-secondary" href="{{ route('portal.ftp.filezilla', $account) }}">
                                <i class="bi bi-filetype-xml me-1"></i>{{ __('portal.filezilla_export') }}
                            </a>@endcan
                        </div>
                    @else
                        <div class="fw-semibold">{{ __('portal.empty') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <h2 class="h5 mb-3">{{ __('portal.edit_mode') }}</h2>
            <form method="POST" action="{{ route('portal.ftp.update', $account) }}" class="vstack gap-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">{{ __('portal.company') }}</label>
                    <select name="company_id" class="form-select" @disabled(! auth()->user()->canPortal('ftp.write'))>
                        <option value="">{{ __('portal.company') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($account->company_id === $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.linked_sites') }}</label>
                    <div class="portal-tags-box" data-tags-input data-tags-name="site_ids" data-tags-placeholder="{{ __('portal.new_tag') }}" data-tags-options='@json($sites->map(fn ($site) => ["id" => $site->id, "label" => $site->name, "search" => trim(($site->url ?? "") . " " . ($site->siteTypeLabel() ?? "") . " " . ($site->environmentLabel() ?? ""))])->values())' data-tags-selected='@json($account->sites->pluck("id")->values())'>
                        <input @readonly(! auth()->user()->canPortal('ftp.write')) type="text" class="portal-tags-input" data-tags-input-field placeholder="{{ __('portal.new_tag') }}">
                        <div class="portal-tags-suggestions d-none" data-tags-suggestions></div>
                        <div class="portal-tags-list" data-tags-list></div>
                        <div data-tags-hidden></div>
                    </div>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.host') }}</label>
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) name="host" class="form-control" value="{{ $account->host }}" required>
                </div>
                <div>
                    <label class="form-label">Port</label>
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) name="port" class="form-control" type="number" value="{{ $account->port }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.login_label') }}</label>
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) name="login" class="form-control" value="{{ $account->login }}" placeholder="{{ __('portal.login_label') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.password') }}</label>
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) name="password" type="password" class="form-control" placeholder="{{ __('portal.password') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.path') }}</label>
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) name="path" class="form-control" value="{{ $account->path }}" placeholder="{{ __('portal.path') }}">
                </div>
                <div class="form-check">
                    <input @readonly(! auth()->user()->canPortal('ftp.write')) id="ftp-ip-access-edit" class="form-check-input" type="checkbox" name="requires_ip_access" value="1" @checked($account->requires_ip_access)>
                    <label class="form-check-label" for="ftp-ip-access-edit">{{ __('portal.requires_ip_access') }}</label>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.note') }}</label>
                    <textarea @readonly(! auth()->user()->canPortal('ftp.write')) name="note" class="form-control" rows="4" placeholder="{{ __('portal.note') }}">{{ $account->note }}</textarea>
                </div>
                @can('ftp.write')<button class="btn btn-dark">{{ __('portal.save') }}</button>@endcan
            </form>
        </div>
    </div>
</div>
@endsection
