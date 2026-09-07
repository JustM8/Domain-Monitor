@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 p-lg-5">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="h4 mb-2">Ваш акаунт очікує підтвердження адміном</h1>
            <div class="portal-soft">Ви ще можете підтвердити email і оновити профіль, поки адмін переглядає заявку.</div>
        </div>
        <span class="badge rounded-pill text-bg-warning">Очікує апруву</span>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="portal-surface p-3 h-100">
                <div class="fw-semibold mb-2">Що далі</div>
                <ul class="mb-0 ps-3">
                    <li>Підтвердіть email із листа, який ми надішлемо.</li>
                    <li>Почекайте, поки адмін підтвердить акаунт.</li>
                    <li>Після підтвердження увійдіть знову і продовжуйте роботу в порталі.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="portal-surface p-3 h-100">
                <div class="fw-semibold mb-2">{{ __('portal.quick_actions') }}</div>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary" href="{{ route('portal.profile.edit') }}">
                        <i class="bi bi-person-gear me-1"></i>{{ __('portal.profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline-secondary w-100">
                            <i class="bi bi-box-arrow-right me-1"></i>{{ __('portal.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
