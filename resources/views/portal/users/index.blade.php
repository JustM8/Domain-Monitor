@extends('layouts.portal')

@section('content')
<div class="portal-card p-4">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.users_list') }}</h2>
            <div class="portal-soft small">{{ __('portal.users_hint') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <a class="btn btn-primary" href="{{ route('portal.users.add') }}">{{ __('portal.user_new') }}</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>{{ __('portal.user_name') }}</th>
                <th>{{ __('portal.full_name') }}</th>
                <th>{{ __('portal.position') }}</th>
                <th>{{ __('portal.email') }}</th>
                <th>{{ __('portal.role') }}</th>
                <th>{{ __('portal.email_verification') }}</th>
                <th>{{ __('portal.active') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->full_name ?? __('portal.empty') }}</td>
                    <td>{{ $user->position ?? __('portal.empty') }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if($user->role)
                            <span class="badge rounded-pill text-bg-light border">{{ $user->role->label }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-warning">Роль не призначено</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-2">
                            <span class="badge rounded-pill text-bg-{{ $user->hasVerifiedEmail() ? 'success' : 'warning' }}">
                                {{ $user->hasVerifiedEmail() ? __('portal.email_verified') : __('portal.email_unverified') }}
                            </span>
                            <span class="badge rounded-pill text-bg-{{ $user->is_active ? 'success' : 'danger' }}">
                                {{ $user->is_active ? __('portal.active') : __('portal.disabled') }}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span>{{ $user->is_active ? __('portal.yes') : __('portal.no') }}</span>
                            <form method="POST" action="{{ route('portal.users.toggle-active', $user) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" @disabled($user->id === auth()->id())>
                                    {{ $user->is_active ? __('portal.freeze') : __('portal.unfreeze') }}
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('portal.users.edit', $user) }}">{{ __('portal.edit') }}</a>
                            @unless($user->hasVerifiedEmail())
                                <form method="POST" action="{{ route('portal.users.send-verification', $user) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-send me-1"></i>{{ __('portal.send_verification') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="portal-soft">{{ __('portal.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
@endsection
