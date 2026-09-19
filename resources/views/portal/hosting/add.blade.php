@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-lg-6">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.hosting_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.hosting_list') }}</div>
        </div>
        @can('hosting.read')<a class="btn btn-outline-secondary" href="{{ route('portal.hosting.index') }}">{{ __('portal.hosting_list') }}</a>@endcan
    </div>
    @can('hosting.write')<form method="POST" action="{{ route('portal.hosting.store') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="form-label">{{ __('portal.name') }}</label>
            <input name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.provider') }}</label>
                <input name="provider" class="form-control" value="{{ old('provider') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.panel_url') }}</label>
                <input name="panel_url" class="form-control" value="{{ old('panel_url') }}">
            </div>
        </div>
        <details class="portal-surface p-3">
            <summary class="fw-semibold">{{ __('portal.additional_fields') }}</summary>
            <div class="mt-3">
                <label class="form-label">{{ __('portal.note') }}</label>
                <textarea name="note" class="form-control" rows="4">{{ old('note') }}</textarea>
            </div>
        </details>
        <div>
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
        </div>
    </form>@endcan
</div>
@endsection
