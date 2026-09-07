@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-0">{{ __('portal.statuses_settings') }}</h2>
                </div>
                <span class="badge text-bg-light">{{ __('portal.settings') }}</span>
            </div>
            <form method="GET" class="mb-3 d-flex gap-2">
                <input name="search" class="form-control" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <form method="POST" action="{{ route('portal.statuses.store') }}" class="vstack gap-2">
                @csrf
                <input name="name" class="form-control" placeholder="{{ __('portal.name') }}" required>
                <div class="row g-2">
                    <div class="col-4">
                        <input name="color" class="form-control form-control-color" type="color" value="#64748b" required>
                    </div>
                    <div class="col-8">
                        <input name="color_value" class="form-control" value="#64748b" placeholder="{{ __('portal.color_value') }}">
                    </div>
                </div>
                <input name="sort_order" class="form-control" type="number" value="0" min="0" max="9999">
                <button class="btn btn-primary">{{ __('portal.save') }}</button>
            </form>

            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <h3 class="h6 mb-0">{{ __('portal.default_statuses') }}</h3>
                </div>
                <form method="POST" action="{{ route('portal.statuses.sync-defaults') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">{{ __('portal.default_statuses_sync') }}</button>
                </form>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @foreach($defaultStatuses as $defaultStatus)
                    <span class="badge rounded-pill px-3 py-2" style="background-color: {{ $defaultStatus['color'] }}; color: #fff;">
                        {{ $defaultStatus['name'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h5 mb-0">{{ __('portal.statuses') }}</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('portal.name') }}</th>
                        <th>{{ __('portal.color') }}</th>
                        <th>{{ __('portal.sort_order') }}</th>
                        <th>{{ __('portal.archived') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($statuses as $status)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                    <div class="fw-semibold">{{ __('portal.status_short') }}</div>
                                    @if($defaultStatuses->contains(fn ($item) => $item['code'] === $status->code))
                                        <span class="badge text-bg-light">{{ __('portal.base_status') }}</span>
                                    @endif
                                </div>
                                <input form="status-edit-{{ $status->id }}" name="name" class="form-control form-control-sm" value="{{ $status->name }}" required>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-circle" style="width: 16px; height: 16px; background: {{ $status->color }};"></span>
                                    <input form="status-edit-{{ $status->id }}" name="color" class="form-control form-control-sm form-control-color" type="color" value="{{ $status->color }}">
                                    <input form="status-edit-{{ $status->id }}" name="color_value" class="form-control form-control-sm" value="{{ $status->color }}">
                                </div>
                            </td>
                            <td>
                                <input form="status-edit-{{ $status->id }}" name="sort_order" class="form-control form-control-sm" type="number" value="{{ $status->sort_order }}" min="0" max="9999">
                            </td>
                            <td>{{ $status->is_archived ? __('portal.yes') : __('portal.no') }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <form id="status-edit-{{ $status->id }}" method="POST" action="{{ route('portal.statuses.update', $status) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm btn-outline-primary">{{ __('portal.save') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('portal.statuses.toggle-archive', $status) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $status->is_archived ? __('portal.restore') : __('portal.archive') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="portal-soft">{{ __('portal.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
