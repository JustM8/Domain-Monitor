@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.hosting_list') }}</h2>
            <div class="portal-soft small">{{ $hostings->total() }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            @can('hosting.write')<a class="btn btn-primary" href="{{ route('portal.hosting.add') }}">{{ __('portal.hosting_new') }}</a>@endcan
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.name') }}</th>
                <th>{{ __('portal.provider') }}</th>
                <th>{{ __('portal.panel_url') }}</th>
                <th>{{ __('portal.accounts') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($hostings as $hosting)
                <tr>
                    <td>{{ $hosting->name }}</td>
                    <td>{{ $hosting->provider ?? __('portal.empty') }}</td>
                    <td>{{ $hosting->panel_url ?? __('portal.empty') }}</td>
                    <td>{{ $hosting->accounts_count }}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            @can('hosting.read')<a class="btn btn-sm btn-outline-primary" href="{{ route('portal.hosting.edit', $hosting) }}">{{ __('portal.edit') }}</a>@endcan
                            @can('hosting.delete')<form method="POST" action="{{ route('portal.hosting.destroy', $hosting) }}" data-delete-confirm data-delete-subject="{{ $hosting->name }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('portal.delete') }}</button>
                            </form>@endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $hostings->links() }}
</div>
@endsection
