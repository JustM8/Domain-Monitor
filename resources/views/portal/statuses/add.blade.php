@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-lg-6">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.status_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.statuses_settings') }}</div>
        </div>
        @can('statuses.read')<a class="btn btn-outline-secondary" href="{{ route('portal.statuses.index') }}">{{ __('portal.statuses') }}</a>@endcan
    </div>
    @can('statuses.write')<form method="POST" action="{{ route('portal.statuses.store') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="form-label">{{ __('portal.name') }}</label>
            <input name="name" class="form-control" required>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">{{ __('portal.color') }}</label>
                <input name="color" class="form-control form-control-color" type="color" value="#64748b" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ __('portal.color_value') }}</label>
                <input name="color_value" class="form-control" value="#64748b">
            </div>
        </div>
        <div>
            <label class="form-label">{{ __('portal.sort_order') }}</label>
            <input name="sort_order" class="form-control" type="number" value="0" min="0" max="9999">
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
            @can('statuses.read')<a class="btn btn-outline-secondary" href="{{ route('portal.statuses.index') }}">{{ __('portal.statuses') }}</a>@endcan
        </div>
    </form>@endcan

    <hr class="my-4">
    <div class="d-flex flex-wrap gap-2">
        @foreach($defaultStatuses as $defaultStatus)
            <span class="badge rounded-pill px-3 py-2" style="background-color: {{ $defaultStatus['color'] }}; color: #fff;">
                {{ $defaultStatus['name'] }}
            </span>
        @endforeach
    </div>
</div>
@endsection
