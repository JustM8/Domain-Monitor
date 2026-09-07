@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-lg-6">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.company_name') }}</h2>
            <div class="portal-soft small">{{ __('portal.company') }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('portal.companies.index') }}">{{ __('portal.companies') }}</a>
    </div>
    <form method="POST" action="{{ route('portal.companies.store') }}" class="vstack gap-3">
        @csrf
        <div>
            <label class="form-label">{{ __('portal.company_name') }}</label>
            <input name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.manager') }}</label>
                <input name="manager_name" class="form-control" value="{{ old('manager_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.contact') }}</label>
                <input name="contact" class="form-control" value="{{ old('contact') }}">
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
    </form>
</div>
@endsection
