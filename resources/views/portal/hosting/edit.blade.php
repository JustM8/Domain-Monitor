@extends('layouts.portal')

@section('content')
<div class="card p-4 col-lg-6">
    <h2 class="h5">{{ __('portal.edit') }}: {{ $hosting->name }}</h2>
    <form method="POST" action="{{ route('portal.hosting.update', $hosting) }}" class="vstack gap-2">
        @csrf
        @method('PUT')
        <input name="name" class="form-control" value="{{ $hosting->name }}" required>
        <input name="provider" class="form-control" value="{{ $hosting->provider }}" placeholder="{{ __('portal.provider') }}">
        <input name="panel_url" class="form-control" value="{{ $hosting->panel_url }}" placeholder="{{ __('portal.panel_url') }}">
        <textarea name="note" class="form-control" rows="4" placeholder="{{ __('portal.note') }}">{{ $hosting->note }}</textarea>
        <button class="btn btn-dark">{{ __('portal.save') }}</button>
    </form>
</div>
@endsection
