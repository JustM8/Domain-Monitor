@extends('layouts.portal')

@section('content')
<div class="card p-4 col-lg-6">
    <h2 class="h5">{{ __('portal.edit') }}: {{ $status->name }}</h2>
    <form method="POST" action="{{ route('portal.statuses.update', $status) }}" class="vstack gap-2">
        @csrf
        @method('PUT')
        <input name="name" class="form-control" value="{{ $status->name }}" required>
        <input name="color" class="form-control" value="{{ $status->color }}" required>
        <input name="sort_order" class="form-control" type="number" value="{{ $status->sort_order }}">
        <button class="btn btn-dark">{{ __('portal.save') }}</button>
    </form>
</div>
@endsection
