@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-xl-10">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.support.client_edit') }}: {{ $client->displayName() }}</h2>
            <div class="portal-soft small">{{ $client->telegram_username ? '@' . ltrim($client->telegram_username, '@') : __('portal.empty') }}</div>
        </div>
        @can('support.read')<a class="btn btn-outline-secondary" href="{{ route('portal.support.clients.index') }}">{{ __('portal.support.clients') }}</a>@endcan
    </div>

    <form method="POST" action="{{ route('portal.support.clients.update', $client) }}">
        @csrf
        @method('PUT')
        @include('portal.support.clients._form', ['client' => $client, 'companies' => $companies])
        <div class="mt-4">
            @can('support.write')<button class="btn btn-primary">{{ __('portal.save') }}</button>@endcan
        </div>
    </form>
</div>
@endsection
