@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.ftp_list') }}</h2>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <a class="btn btn-primary" href="{{ route('portal.ftp.add') }}">{{ __('portal.ftp_new') }}</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.company') }}</th>
                <th>{{ __('portal.site') }}</th>
                <th>{{ __('portal.host') }}</th>
                <th>{{ __('portal.login_label') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->company?->name ?? __('portal.empty') }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @php
                                $linkedSites = $account->sites->take(3);
                                $remainingSites = max(0, $account->sites->count() - $linkedSites->count());
                            @endphp
                            @forelse($linkedSites as $site)
                                <span class="badge text-bg-light border">{{ $site->name }}</span>
                            @empty
                                <span class="portal-soft">{{ __('portal.empty') }}</span>
                            @endforelse
                            @if($remainingSites > 0)
                                <span class="badge text-bg-light border">+{{ $remainingSites }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $account->host }}:{{ $account->port }}</td>
                    <td>{{ $account->login }}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.ftp.edit', $account) }}">{{ __('portal.edit') }}</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.ftp.filezilla', $account) }}">
                                {{ __('portal.filezilla_export') }}
                            </a>
                            <form method="POST" action="{{ route('portal.ftp.destroy', $account) }}" data-delete-confirm data-delete-subject="{{ $account->company?->name ?? $account->host }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('portal.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $accounts->links() }}
</div>
@endsection
