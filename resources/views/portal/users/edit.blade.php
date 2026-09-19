@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-xl-7">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="h5 mb-1">{{ __('portal.edit_user') }}</h2>
                    <div class="portal-soft small">{{ __('portal.user_edit_hint') }}</div>
                </div>
                <div class="d-flex flex-column gap-2 align-items-start">
                    <span class="badge rounded-pill text-bg-{{ $user->hasVerifiedEmail() ? 'success' : 'warning' }}">
                        {{ $user->hasVerifiedEmail() ? __('portal.email_verified') : __('portal.email_unverified') }}
                    </span>
                    <span class="badge rounded-pill text-bg-{{ $user->is_active ? 'success' : 'danger' }}">
                        {{ $user->is_active ? __('portal.active') : __('portal.disabled') }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('portal.users.update', $user) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.user_name') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) name="name" class="form-control" value="{{ $user->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.email') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) name="email" class="form-control" type="email" value="{{ $user->email }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.full_name') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) name="full_name" class="form-control" value="{{ $user->full_name }}" placeholder="{{ __('portal.full_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.position') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) name="position" class="form-control" value="{{ $user->position }}" placeholder="{{ __('portal.position') }}">
                </div>

                <div class="col-12">
                    <div class="portal-surface p-3">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                            <div>
                                <div class="fw-semibold">{{ __('portal.role') }}</div>
                                <div class="small portal-soft">Адмін може вибрати існуючу роль або створити нову для цього користувача.</div>
                            </div>
                            @if($roles->isEmpty())
                                <span class="badge rounded-pill text-bg-warning">Ролей ще немає</span>
                            @endif
                        </div>

                        @if(auth()->user()->isAdmin() && $user->id !== auth()->id())
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Існуюча роль</label>
                                    <select name="role_id" class="form-select" @disabled(! auth()->user()->canPortal('users.write'))>
                                        <option value="">Оберіть роль</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Нова роль</label>
                                    <input @readonly(! auth()->user()->canPortal('users.write')) name="new_role_name" class="form-control" value="{{ old('new_role_name') }}" placeholder="Наприклад: designer">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Назва ролі</label>
                                    <input @readonly(! auth()->user()->canPortal('users.write')) name="new_role_label" class="form-control" value="{{ old('new_role_label') }}" placeholder="Наприклад: Дизайнер">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Порядок</label>
                                    <input @readonly(! auth()->user()->canPortal('users.write')) name="new_role_sort_order" class="form-control" type="number" min="0" max="9999" value="{{ old('new_role_sort_order', 0) }}">
                                </div>
                            </div>
                        @else
                            <input @readonly(! auth()->user()->canPortal('users.write')) class="form-control" value="{{ $user->role?->label ?? __('portal.empty') }}" readonly>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.email_verification') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) class="form-control" value="{{ $user->email_verified_at?->format('d.m.Y H:i') ?? __('portal.not_verified') }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.new_password') }}</label>
                    <div class="input-group">
                        <input @readonly(! auth()->user()->canPortal('users.write')) id="user-password" name="password" class="form-control" type="password" placeholder="{{ __('portal.new_password') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.password_confirm') }}</label>
                    <input @readonly(! auth()->user()->canPortal('users.write')) id="user-password-confirmation" name="password_confirmation" class="form-control" type="password" placeholder="{{ __('portal.password_confirm') }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input @readonly(! auth()->user()->canPortal('users.write')) class="form-check-input" type="checkbox" name="is_active" value="1" id="user-active" @checked($user->is_active) @disabled($user->id === auth()->id())>
                        <label class="form-check-label" for="user-active">{{ __('portal.active') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    @can('users.write')<button class="btn btn-primary px-4">{{ __('portal.save') }}</button>@endcan
                </div>
            </form>

            @if(! $user->hasVerifiedEmail())
                @can('users.write')<form method="POST" action="{{ route('portal.users.send-verification', $user) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-outline-success text-nowrap">
                        <i class="bi bi-send me-1"></i>{{ __('portal.send_verification') }}
                    </button>
                </form>@endcan
            @endif
        </div>
    </div>

    <div class="col-xl-5">
        <div class="portal-card p-4 h-100">
            <h3 class="h5 mb-3">{{ __('portal.user_card') }}</h3>
            <div class="vstack gap-3">
                <div><div class="portal-soft small">{{ __('portal.last_login') }}</div><div class="fw-semibold">{{ $user->last_login_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div></div>
                <div><div class="portal-soft small">{{ __('portal.created_at') }}</div><div class="fw-semibold">{{ $user->created_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div></div>
                <div><div class="portal-soft small">{{ __('portal.account_status') }}</div><div><span class="badge rounded-pill text-bg-{{ $user->is_active ? 'success' : 'danger' }}">{{ $user->is_active ? __('portal.active') : __('portal.disabled') }}</span></div></div>
                <div><div class="portal-soft small">{{ __('portal.role') }}</div><div class="fw-semibold">{{ $user->role?->label ?? __('portal.empty') }}</div></div>
            </div>

            <div class="portal-surface p-3 mt-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="fw-semibold">{{ __('portal.telegram_block_title') }}</div>
                        <div class="small portal-soft">{{ __('portal.telegram_block_hint_admin') }}</div>
                    </div>
                    <span class="badge rounded-pill text-bg-{{ $user->telegramIsLinked() ? 'success' : ($user->telegramNeedsBotStart() ? 'info' : ($user->telegramIsPending() ? 'warning' : 'secondary')) }}">
                        @if($user->telegramIsLinked())
                            {{ __('portal.telegram_connected') }}
                        @elseif($user->telegramNeedsBotStart())
                            {{ __('portal.telegram_approved_waiting') }}
                        @elseif($user->telegramIsPending())
                            {{ __('portal.telegram_pending') }}
                        @else
                            {{ __('portal.telegram_not_connected') }}
                        @endif
                    </span>
                </div>

                <div class="mt-3 vstack gap-2">
                    <div class="small">
                        <span class="portal-soft">{{ __('portal.telegram_username') }}:</span>
                        <span class="fw-semibold">{{ $user->telegram_username ?? __('portal.empty') }}</span>
                    </div>
                    <div class="small">
                        <span class="portal-soft">{{ __('portal.telegram_chat_id') }}:</span>
                        <span class="fw-semibold">{{ $user->telegram_chat_id ?? __('portal.empty') }}</span>
                    </div>
                    <div class="small">
                        <span class="portal-soft">{{ __('portal.telegram_link_requested_at') }}:</span>
                        <span class="fw-semibold">{{ $user->telegram_link_requested_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</span>
                    </div>
                    <div class="small">
                        <span class="portal-soft">{{ __('portal.telegram_verified_at') }}:</span>
                        <span class="fw-semibold">{{ $user->telegram_verified_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</span>
                    </div>
                </div>

                @if($user->telegramIsPending())
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        @can('users.write')<form method="POST" action="{{ route('portal.users.telegram.approve', $user) }}">
                            @csrf
                            <button class="btn btn-sm btn-primary">{{ __('portal.telegram_approve') }}</button>
                        </form>@endcan
                        @can('users.write')<form method="POST" action="{{ route('portal.users.telegram.revoke', $user) }}" data-delete-confirm data-delete-subject="{{ $user->displayName() }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">{{ __('portal.telegram_revoke') }}</button>
                        </form>@endcan
                    </div>
                @elseif($user->telegramNeedsBotStart())
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <div class="small portal-soft">Користувач підключає Telegram лише у власному кабінеті.</div>
                        @can('users.write')<form method="POST" action="{{ route('portal.users.telegram.revoke', $user) }}" data-delete-confirm data-delete-subject="{{ $user->displayName() }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">{{ __('portal.telegram_revoke') }}</button>
                        </form>@endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
