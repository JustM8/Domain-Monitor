@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 col-lg-8">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.user_new') }}</h2>
            <div class="portal-soft small">{{ __('portal.users_hint') }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('portal.users.index') }}">{{ __('portal.users_list') }}</a>
    </div>

    <form method="POST" action="{{ route('portal.users.store') }}" class="vstack gap-3">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.user_name') }}</label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.email') }}</label>
                <input name="email" class="form-control" type="email" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.full_name') }}</label>
                <input name="full_name" class="form-control" value="{{ old('full_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.position') }}</label>
                <input name="position" class="form-control" value="{{ old('position') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.password') }}</label>
                <input name="password" class="form-control" type="text" required autocomplete="off" spellcheck="false">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('portal.password_confirm') }}</label>
                <input name="password_confirmation" class="form-control" type="text" required autocomplete="off" spellcheck="false">
            </div>
        </div>

        <div class="portal-surface p-3">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <div>
                    <div class="fw-semibold">{{ __('portal.role') }}</div>
                    <div class="small portal-soft">Можна вибрати існуючу роль або створити нову прямо тут.</div>
                </div>
                @if($roles->isEmpty())
                    <span class="badge rounded-pill text-bg-warning">Роль ще не створено</span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Існуюча роль</label>
                    <select name="role_id" class="form-select">
                        <option value="">Оберіть роль</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Нова роль</label>
                    <input name="new_role_name" class="form-control" value="{{ old('new_role_name') }}" placeholder="Наприклад: designer">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Назва ролі</label>
                    <input name="new_role_label" class="form-control" value="{{ old('new_role_label') }}" placeholder="Наприклад: Дизайнер">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Порядок</label>
                    <input name="new_role_sort_order" class="form-control" type="number" min="0" max="9999" value="{{ old('new_role_sort_order', 0) }}">
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="user-active-add">
                <label class="form-check-label" for="user-active-add">{{ __('portal.active') }}</label>
            </div>
        </div>

        <div>
            <button class="btn btn-primary">{{ __('portal.save') }}</button>
        </div>
    </form>
</div>
@endsection
