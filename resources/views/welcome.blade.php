@extends('layouts.app')

@section('content')
<div class="row align-items-center g-4 py-4 py-lg-5">
    <div class="col-lg-6">
        <div class="guest-badge mb-3">
            <i class="bi bi-shield-check"></i>
            {{ __('portal.guest_badge') }}
        </div>
        <h1 class="display-5 fw-bold mb-3">{{ __('portal.guest_title') }}</h1>
        <p class="lead text-secondary mb-4">{{ __('portal.guest_subtitle') }}</p>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('login') }}" class="btn btn-dark btn-lg rounded-pill px-4">
                <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('portal.login') }}
            </a>
            @if(Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">
                    <i class="bi bi-person-plus me-1"></i>Реєстрація
                </a>
            @endif
            <span class="guest-badge bg-white text-primary">
                <i class="bi bi-graph-up-arrow"></i>
                {{ __('portal.guest_stats_hint') }}
            </span>
        </div>
        <div class="row g-3 mt-4">
            <div class="col-sm-4">
                <div class="guest-card p-3 h-100">
                    <div class="small text-secondary">{{ __('portal.sites') }}</div>
                    <div class="fs-3 fw-bold">300+</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="guest-card p-3 h-100">
                    <div class="small text-secondary">{{ __('portal.quick_actions') }}</div>
                    <div class="fs-3 fw-bold">1</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="guest-card p-3 h-100">
                    <div class="small text-secondary">{{ __('portal.audit') }}</div>
                    <div class="fs-3 fw-bold">100%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="guest-card p-4 p-lg-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">{{ __('portal.guest_card_title') }}</h2>
                <span class="badge text-bg-primary">{{ __('portal.title') }}</span>
            </div>
            <div class="vstack gap-3">
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0 fs-4 text-primary"><i class="bi bi-sliders2"></i></div>
                    <div>
                        <div class="fw-semibold">{{ __('portal.guest_point_1_title') }}</div>
                        <div class="text-secondary">{{ __('portal.guest_point_1_text') }}</div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0 fs-4 text-primary"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="fw-semibold">{{ __('portal.guest_point_2_title') }}</div>
                        <div class="text-secondary">{{ __('portal.guest_point_2_text') }}</div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0 fs-4 text-primary"><i class="bi bi-key"></i></div>
                    <div>
                        <div class="fw-semibold">{{ __('portal.guest_point_3_title') }}</div>
                        <div class="text-secondary">{{ __('portal.guest_point_3_text') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
