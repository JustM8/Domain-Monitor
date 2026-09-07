@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="portal-card p-4">
            <h2 class="h5 mb-3">{{ __('portal.edit') }}: {{ $company->name }}</h2>
            <form method="POST" action="{{ route('portal.companies.update', $company) }}" class="vstack gap-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">{{ __('portal.company_name') }}</label>
                    <input name="name" class="form-control" value="{{ $company->name }}" required>
                </div>
                <div>
                    <label class="form-label">{{ __('portal.manager') }}</label>
                    <input name="manager_name" class="form-control" value="{{ $company->manager_name }}" placeholder="{{ __('portal.manager') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.contact') }}</label>
                    <input name="contact" class="form-control" value="{{ $company->contact }}" placeholder="{{ __('portal.contact') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('portal.note') }}</label>
                    <textarea name="note" class="form-control" rows="4" placeholder="{{ __('portal.note') }}">{{ $company->note }}</textarea>
                </div>
                <button class="btn btn-dark">{{ __('portal.save') }}</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="portal-card p-4 mb-3">
            <h2 class="h5 mb-3">{{ __('portal.company_assets') }}</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="portal-soft small">{{ __('portal.sites') }}</div>
                    <div class="fw-semibold">{{ $company->sites->count() }}</div>
                </div>
                <div class="col-md-4">
                    <div class="portal-soft small">{{ __('portal.ftp') }}</div>
                    <div class="fw-semibold">{{ $company->ftpAccounts->count() }}</div>
                </div>
                <div class="col-md-4">
                    <div class="portal-soft small">{{ __('portal.hosting_accounts') }}</div>
                    <div class="fw-semibold">{{ $company->hostingAccounts->count() }}</div>
                </div>
            </div>
        </div>

        <div class="portal-card p-4 mb-3">
            <h2 class="h5 mb-3">{{ __('portal.sites') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @forelse($company->sites as $site)
                    <a class="badge text-bg-light border text-decoration-none" href="{{ route('portal.sites.show', $site) }}">
                        {{ $site->name }} | {{ $site->siteTypeLabel() }} | {{ $site->environmentLabel() }}
                    </a>
                @empty
                    <div class="portal-soft">{{ __('portal.empty') }}</div>
                @endforelse
            </div>
        </div>

        <div class="portal-card p-4 mb-3">
            <h2 class="h5 mb-3">{{ __('portal.ftp') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @forelse($company->ftpAccounts as $ftp)
                    <a class="badge text-bg-light border text-decoration-none" href="{{ route('portal.ftp.edit', $ftp) }}">
                        {{ $ftp->host }}:{{ $ftp->port }}
                    </a>
                @empty
                    <div class="portal-soft">{{ __('portal.empty') }}</div>
                @endforelse
            </div>
        </div>

        <div class="portal-card p-4">
            <h2 class="h5 mb-3">{{ __('portal.hosting_accounts') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @forelse($company->hostingAccounts as $hostingAccount)
                    <a class="badge text-bg-light border text-decoration-none" href="{{ route('portal.hosting-accounts.edit', $hostingAccount) }}">
                        {{ $hostingAccount->title }}
                    </a>
                @empty
                    <div class="portal-soft">{{ __('portal.empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
