@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.support.clients') }}</h2>
            <div class="portal-soft small">Клієнти, їх компанії та статус верифікації по Telegram.</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" value="{{ $search }}" placeholder="{{ __('portal.search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            @can('support.write')<a class="btn btn-primary" href="{{ route('portal.support.clients.add') }}">{{ __('portal.support.client_new') }}</a>@endcan
        </div>
    </div>
</div>

<div class="portal-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>{{ __('portal.client') }}</th>
                <th>{{ __('portal.company') }}</th>
                <th>{{ __('portal.support.telegram') }}</th>
                <th>{{ __('portal.support.state') }}</th>
                <th>{{ __('portal.description') }}</th>
                <th>{{ __('portal.created_at') }}</th>
                <th>{{ __('portal.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $client->displayName() }}</div>
                        <div class="small portal-soft">
                            {{ $client->email ?? __('portal.empty') }}
                            @if($client->phone)
                                · {{ $client->phone }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $client->companyDisplayName() }}</div>
                        @if($client->requiresCompanyValidation())
                            <div class="small text-warning">Потрібна валідація адміном</div>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $client->telegram_username ? '@' . ltrim($client->telegram_username, '@') : __('portal.empty') }}</div>
                        <div class="small portal-soft">{{ $client->telegram_chat_id }}</div>
                    </td>
                    <td>{{ $client->stateLabel() }}</td>
                    <td>
                        <div class="small text-truncate" style="max-width: 320px;">
                            {{ $client->description ?: __('portal.empty') }}
                        </div>
                    </td>
                    <td class="text-nowrap">{{ $client->last_active_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</td>
                    <td class="text-nowrap">
                        @can('support.read')<a class="btn btn-sm btn-primary" href="{{ route('portal.support.clients.edit', $client) }}">{{ __('portal.edit') }}</a>@endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="portal-soft">{{ __('portal.empty') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $clients->links() }}
    </div>
</div>
@endsection
