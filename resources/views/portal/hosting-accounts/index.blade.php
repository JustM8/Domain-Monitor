@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.hosting_accounts') }}</h2>
            <div class="portal-soft small">{{ $accounts->total() }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <a class="btn btn-primary" href="{{ route('portal.hosting-accounts.add') }}">{{ __('portal.hosting_account_new') }}</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.company') }}</th>
                <th>{{ __('portal.name') }}</th>
                <th>{{ __('portal.hosting') }}</th>
                <th>{{ __('portal.site') }}</th>
                <th>{{ __('portal.login_label') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->company?->name ?? __('portal.empty') }}</td>
                    <td>{{ $account->title }}</td>
                    <td>{{ $account->hosting?->name ?? __('portal.empty') }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($account->sites as $site)
                                <span class="badge text-bg-light border">{{ $site->name }}</span>
                            @empty
                                <span class="portal-soft">{{ __('portal.empty') }}</span>
                            @endforelse
                        </div>
                    </td>
                    <td>{{ $account->login }}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.hosting-accounts.edit', $account) }}">{{ __('portal.edit') }}</a>
                            <form method="POST" action="{{ route('portal.hosting-accounts.destroy', $account) }}" data-delete-confirm data-delete-subject="{{ $account->title }}">
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

    {{ $accounts->links() }}
</div>
@endsection
