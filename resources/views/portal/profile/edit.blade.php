@extends('layouts.portal')

@section('content')
<div class="row g-3">
    <div class="col-xl-7">
        <div class="portal-card p-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap">
                <div>
                    <h2 class="h4 mb-1">{{ __('portal.profile') }}</h2>
                    <div class="portal-soft">{{ __('portal.profile_hint') }}</div>
                </div>
                <span class="badge rounded-pill text-bg-{{ auth()->user()->hasVerifiedEmail() ? 'success' : 'warning' }}">
                    {{ auth()->user()->hasVerifiedEmail() ? __('portal.email_verified') : __('portal.email_unverified') }}
                </span>
            </div>

            @unless(auth()->user()->is_active)
                <div class="alert alert-warning">
                    Ваш акаунт очікує підтвердження адміном.
                </div>
            @endunless

            <form method="POST" action="{{ route('portal.profile.update') }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.user_name') }}</label>
                    <input name="name" class="form-control" value="{{ auth()->user()->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.email') }}</label>
                    <input class="form-control" value="{{ auth()->user()->email }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.full_name') }}</label>
                    <input name="full_name" class="form-control" value="{{ auth()->user()->full_name }}" placeholder="{{ __('portal.full_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.position') }}</label>
                    <input name="position" class="form-control" value="{{ auth()->user()->position }}" placeholder="{{ __('portal.position') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.role') }}</label>
                    <input class="form-control" value="{{ auth()->user()->role?->label ?? __('portal.empty') }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.email_verification') }}</label>
                    <input class="form-control" value="{{ auth()->user()->email_verified_at?->format('d.m.Y H:i') ?? __('portal.not_verified') }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.new_password') }}</label>
                    <div class="input-group">
                        <input id="profile-password" name="password" class="form-control" type="password" placeholder="{{ __('portal.new_password') }}">
                        <button class="btn btn-outline-secondary" type="button" data-password-target="#profile-password" data-password-confirm="#profile-password-confirmation">
                            <i class="bi bi-shuffle"></i> {{ __('portal.generate_password') }}
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('portal.password_confirm') }}</label>
                    <input id="profile-password-confirmation" name="password_confirmation" class="form-control" type="password" placeholder="{{ __('portal.password_confirm') }}">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary px-4">{{ __('portal.save') }}</button>
                </div>
            </form>

            @unless(auth()->user()->hasVerifiedEmail())
                <form method="POST" action="{{ route('portal.profile.verification.send') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-outline-primary text-nowrap">
                        <i class="bi bi-send me-1"></i>{{ __('portal.send_verification') }}
                    </button>
                </form>
            @endunless

            <div class="portal-surface p-3 mt-3" style="border-left: 4px solid var(--portal-primary);">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div class="d-flex align-items-start gap-3">
                        <div class="portal-stat-icon">
                            <i class="bi bi-telegram"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">{{ __('portal.telegram_block_title') }}</div>
                            <div class="small portal-soft">{{ __('portal.telegram_block_hint') }}</div>
                            @if(! empty($telegramBotUsername))
                                <div class="small mt-1">
                                    <span class="portal-soft">{{ __('portal.telegram_username') }}:</span>
                                    <span class="fw-semibold">{{ '@' . $telegramBotUsername }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <span class="badge rounded-pill text-bg-{{ auth()->user()->telegramIsLinked() ? 'success' : (auth()->user()->telegramNeedsBotStart() ? 'info' : (auth()->user()->telegramIsPending() ? 'warning' : 'secondary')) }}">
                        @if(auth()->user()->telegramIsLinked())
                            {{ __('portal.telegram_connected') }}
                        @elseif(auth()->user()->telegramNeedsBotStart())
                            {{ __('portal.telegram_approved_waiting') }}
                        @elseif(auth()->user()->telegramIsPending())
                            {{ __('portal.telegram_pending') }}
                        @else
                            {{ __('portal.telegram_not_connected') }}
                        @endif
                    </span>
                </div>

                <div class="row g-2 mt-3">
                    <div class="col-md-4">
                        <div class="portal-empty h-100 p-2">
                            <div class="small portal-soft">{{ __('portal.telegram_username') }}</div>
                            <div class="fw-semibold">{{ auth()->user()->telegram_username ? '@'.auth()->user()->telegram_username : __('portal.empty') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="portal-empty h-100 p-2">
                            <div class="small portal-soft">{{ __('portal.telegram_link_requested_at') }}</div>
                            <div class="fw-semibold">{{ auth()->user()->telegram_link_requested_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="portal-empty h-100 p-2">
                            <div class="small portal-soft">{{ __('portal.telegram_verified_at') }}</div>
                            <div class="fw-semibold">{{ auth()->user()->telegram_verified_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    @if((auth()->user()->telegramIsPending() || auth()->user()->telegramNeedsBotStart()) && auth()->user()->telegram_link_token && ! empty($telegramBotUsername))
                        <a class="btn btn-outline-secondary" href="https://t.me/{{ $telegramBotUsername }}?start={{ auth()->user()->telegram_link_token }}" target="_blank" rel="noopener">
                            <i class="bi bi-telegram me-1"></i>{{ __('portal.telegram_open_bot') }}
                        </a>
                    @elseif(! auth()->user()->telegramIsLinked())
                        <form method="POST" action="{{ route('portal.profile.telegram.request') }}">
                            @csrf
                            <button class="btn btn-primary">
                                <i class="bi bi-telegram me-1"></i>{{ __('portal.telegram_request_access') }}
                            </button>
                        </form>
                    @else
                        <span class="portal-soft small align-self-center">{{ __('portal.telegram_access_ready', ['name' => auth()->user()->displayName()]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="portal-card p-4 h-100">
            <h3 class="h5 mb-3">{{ __('portal.security') }}</h3>
            <div class="vstack gap-3">
                <div>
                    <div class="portal-soft small">{{ __('portal.last_login') }}</div>
                    <div class="fw-semibold">{{ auth()->user()->last_login_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.account_status') }}</div>
                    <div class="fw-semibold">
                        <span class="badge rounded-pill text-bg-{{ auth()->user()->is_active ? 'success' : 'danger' }}">
                            {{ auth()->user()->is_active ? __('portal.active') : __('portal.disabled') }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.verified_hint') }}</div>
                    <div class="text-secondary">{{ __('portal.verified_description') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-password-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.querySelector(button.dataset.passwordTarget);
            const confirmation = document.querySelector(button.dataset.passwordConfirm);
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
            const length = 16;
            let password = '';

            for (let i = 0; i < length; i++) {
                password += chars[Math.floor(Math.random() * chars.length)];
            }

            if (target) target.value = password;
            if (confirmation) confirmation.value = password;

            try {
                await navigator.clipboard.writeText(password);
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-success');
                setTimeout(() => {
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-secondary');
                }, 1200);
            } catch (e) {
                // clipboard may be unavailable in some browsers
            }
        });
    });
</script>
@endpush
