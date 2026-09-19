@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-xl-10">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.support.client_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.support.client_form_hint') }}</div>
        </div>
        @can('support.read')<a class="btn btn-outline-secondary" href="{{ route('portal.support.clients.index') }}">{{ __('portal.support.clients') }}</a>@endcan
    </div>

    @can('support.write')<form method="POST" action="{{ route('portal.support.clients.store') }}">
        @csrf
        @include('portal.support.clients._form', ['client' => $client, 'companies' => $companies])
        <div class="mt-4">
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
        </div>
    </form>@endcan
</div>
@endsection
