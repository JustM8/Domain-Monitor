@extends('layouts.portal')

@section('content')
<div class="card p-4 col-lg-6">
    <h2 class="h5">{{ __('portal.edit') }}: {{ $status->name }}</h2>
    <form method="POST" action="{{ route('portal.statuses.update', $status) }}" class="vstack gap-2">
        @csrf
        @method('PUT')
        <input @readonly(! auth()->user()->canPortal('statuses.write')) name="name" class="form-control" value="{{ $status->name }}" required>
        <input @readonly(! auth()->user()->canPortal('statuses.write')) name="color" class="form-control" value="{{ $status->color }}" required>
        <input @readonly(! auth()->user()->canPortal('statuses.write')) name="sort_order" class="form-control" type="number" value="{{ $status->sort_order }}">
        @can('statuses.write')<button class="btn btn-dark">{{ __('portal.save') }}</button>@endcan
    </form>
</div>
@endsection
