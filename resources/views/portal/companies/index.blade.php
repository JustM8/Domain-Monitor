@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.companies') }}</h2>
            <div class="portal-soft small">{{ $companies->total() }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <a class="btn btn-primary" href="{{ route('portal.companies.add') }}">{{ __('portal.company_new') }}</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.name') }}</th>
                <th>{{ __('portal.sites') }}</th>
                <th>{{ __('portal.ftp') }}</th>
                <th>{{ __('portal.hosting_accounts') }}</th>
                <th>{{ __('portal.contact') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($companies as $company)
                <tr>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->sites_count }}</td>
                    <td>{{ $company->ftp_accounts_count }}</td>
                    <td>{{ $company->hosting_accounts_count }}</td>
                    <td>{{ $company->contact ?? __('portal.empty') }}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.companies.edit', $company) }}">{{ __('portal.edit') }}</a>
                            <form method="POST" action="{{ route('portal.companies.destroy', $company) }}" data-delete-confirm data-delete-subject="{{ $company->name }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('portal.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $companies->links() }}
</div>
@endsection
