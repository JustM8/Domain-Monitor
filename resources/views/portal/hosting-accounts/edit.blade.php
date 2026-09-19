@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <h2 class="h5 mb-0">{{ __('portal.edit') }}: {{ $account->title }}</h2>
                <span class="badge text-bg-light">{{ __('portal.read_only_view') }}</span>
            </div>
            <div class="vstack gap-3">
                <div>
                    <div class="portal-soft small">{{ __('portal.company') }}</div>
                    <div class="fw-semibold">{{ $account->company?->name ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.hosting') }}</div>
                    <div class="fw-semibold">{{ $account->hosting?->name }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.linked_sites') }}</div>
                    <div class="d-flex flex-wrap gap-1">
                        @forelse($account->sites as $site)
                            <span class="badge text-bg-light border">{{ $site->name }}</span>
                        @empty
                            <span class="portal-soft">{{ __('portal.empty') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.login_label') }}</div>
                        <div class="fw-semibold">{{ $account->login ?? __('portal.empty') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.ssh_host') }}</div>
                        <div class="fw-semibold">{{ $account->ssh_host ?? __('portal.empty') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.ssh_login') }}</div>
                        <div class="fw-semibold">{{ $account->ssh_login ?? __('portal.empty') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-soft small">{{ __('portal.ssh_port') }}</div>
                        <div class="fw-semibold">{{ $account->ssh_port ?? __('portal.empty') }}</div>
                    </div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.password') }}</div>
                    @if($passwordValue)
                        <div class="input-group mb-2">
                            <input id="hosting-password-view" type="password" class="form-control portal-readonly portal-secret-field" readonly value="{{ $passwordValue }}">
                            <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#hosting-password-view">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-secret-copy="#hosting-password-view">
                                <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                            </button>
                        </div>
                    @else
                        <div class="fw-semibold">{{ __('portal.empty') }}</div>
                    @endif
                    <div class="portal-soft small">{{ __('portal.ssh_password') }}</div>
                    @if($sshPasswordValue)
                        <div class="input-group">
                            <input id="hosting-ssh-password-view" type="password" class="form-control portal-readonly portal-secret-field" readonly value="{{ $sshPasswordValue }}">
                            <button class="btn btn-outline-secondary" type="button" data-secret-toggle="#hosting-ssh-password-view">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-secret-copy="#hosting-ssh-password-view">
                                <i class="bi bi-copy"></i> {{ __('portal.copy') }}
                            </button>
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
            <form method="POST" action="{{ route('portal.hosting-accounts.update', $account) }}" class="vstack gap-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">{{ __('portal.company') }}</label>
                    <select name="company_id" class="form-select" @disabled(! auth()->user()->canPortal('hosting-accounts.write'))>
                        <option value="">{{ __('portal.company') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($account->company_id === $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.hosting') }}</label>
                    <select name="hosting_id" class="form-select" required @disabled(! auth()->user()->canPortal('hosting-accounts.write'))>
                        @foreach($hostings as $hosting)
                            <option value="{{ $hosting->id }}" @selected($account->hosting_id === $hosting->id)>{{ $hosting->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.linked_sites') }}</label>
                    <div class="portal-tags-box" data-tags-input data-tags-name="site_ids" data-tags-placeholder="{{ __('portal.new_tag') }}" data-tags-options='@json($sites->map(fn ($site) => ["id" => $site->id, "label" => $site->name, "search" => trim(($site->url ?? "") . " " . ($site->siteTypeLabel() ?? "") . " " . ($site->environmentLabel() ?? ""))])->values())' data-tags-selected='@json($account->sites->pluck("id")->values())'>
                        <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) type="text" class="portal-tags-input" data-tags-input-field placeholder="{{ __('portal.new_tag') }}">
                        <div class="portal-tags-suggestions d-none" data-tags-suggestions></div>
                        <div class="portal-tags-list" data-tags-list></div>
                        <div data-tags-hidden></div>
                    </div>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.name') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="title" class="form-control" value="{{ $account->title }}" required>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.login_label') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="login" class="form-control" value="{{ $account->login }}" placeholder="{{ __('portal.login_label') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.password') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="password" class="form-control" placeholder="{{ __('portal.password') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.ssh_host') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="ssh_host" class="form-control" value="{{ $account->ssh_host }}" placeholder="{{ __('portal.ssh_host') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.ssh_port') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="ssh_port" class="form-control" type="number" value="{{ $account->ssh_port }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.ssh_login') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="ssh_login" class="form-control" value="{{ $account->ssh_login }}" placeholder="{{ __('portal.ssh_login') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.ssh_password') }}</label>
                    <input @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="ssh_password" class="form-control" placeholder="{{ __('portal.ssh_password') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.note') }}</label>
                    <textarea @readonly(! auth()->user()->canPortal('hosting-accounts.write')) name="note" class="form-control" rows="4" placeholder="{{ __('portal.note') }}">{{ $account->note }}</textarea>
                </div>
                @can('hosting-accounts.write')<button class="btn btn-dark">{{ __('portal.save') }}</button>@endcan
            </form>
        </div>
    </div>
</div>
@endsection
