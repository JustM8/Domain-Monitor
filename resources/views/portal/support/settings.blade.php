@extends('layouts.portal')

@section('page_title', __('portal.support.settings'))
@section('page_subtitle', __('portal.support.settings_hint'))

@section('content')
@php
    $topicRows = old('topics');

    if (! is_array($topicRows)) {
        $topicRows = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'label' => $topic->label,
                'telegram_chat_id' => $topic->telegram_chat_id,
                'sort_order' => $topic->sort_order,
                'assigned_role' => $topic->assigned_role,
                'is_active' => $topic->is_active ? 1 : 0,
                'responsible_user_ids' => $topic->responsibleUsers->pluck('id')->all(),
            ];
        })->values()->all();
    }
@endphp

<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.support.settings') }}</h2>
            <div class="portal-soft small">{{ __('portal.support.settings_hint') }}</div>
        </div>
        <span class="portal-chip">
            <i class="bi bi-list-check"></i>
            <span>{{ count($topicRows) }}</span>
        </span>
    </div>
</div>

<form method="POST" action="{{ route('portal.support.settings.update') }}" id="support-settings-form">
    @csrf

    <div class="portal-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <div>
                <div class="fw-semibold">{{ __('portal.support.settings') }}</div>
                <div class="portal-soft small">{{ __('portal.support.topic_hint') }}</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="support-topic-add">
                <i class="bi bi-plus-lg me-1"></i>{{ __('portal.support.add_topic') }}
            </button>
        </div>

        <div class="vstack gap-2" id="support-topic-rows">
            @forelse($topicRows as $index => $row)
                <details class="portal-surface p-3" data-topic-row @if($index === 0) open @endif>
                    <summary class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold">{{ __('portal.support.topic') }} #{{ $index + 1 }}</div>
                            <div class="small portal-soft">
                                {{ $row['id'] ? 'ID: ' . $row['id'] : __('portal.support.new_topic') }}
                                @if(!empty($row['telegram_chat_id']))
                                    · {{ $row['telegram_chat_id'] }}
                                @endif
                            </div>
                        </div>
                        <label class="form-check form-switch m-0">
                            <input type="checkbox" class="form-check-input" name="topics[{{ $index }}][is_active]" value="1" @checked((bool) ($row['is_active'] ?? false))>
                            <span class="form-check-label small">{{ __('portal.active') }}</span>
                        </label>
                    </summary>

                    <input type="hidden" name="topics[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">

                    <div class="row g-2 mt-3">
                        <div class="col-lg-5">
                            <label class="form-label mb-1">{{ __('portal.name') }}</label>
                            <input type="text" name="topics[{{ $index }}][label]" class="form-control form-control-sm" value="{{ $row['label'] ?? '' }}" placeholder="{{ __('portal.support.topic_name_placeholder') }}">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label mb-1">{{ __('portal.support.chat_id') }}</label>
                            <input type="text" name="topics[{{ $index }}][telegram_chat_id]" class="form-control form-control-sm" value="{{ $row['telegram_chat_id'] ?? '' }}" placeholder="{{ __('portal.support.chat_id_placeholder') }}">
                        </div>
                        <div class="col-lg-1">
                            <label class="form-label mb-1">{{ __('portal.sort_order') }}</label>
                            <input type="number" name="topics[{{ $index }}][sort_order]" class="form-control form-control-sm" min="0" value="{{ $row['sort_order'] ?? 0 }}">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label mb-1">{{ __('portal.support.fallback_role') }}</label>
                            <input type="text" name="topics[{{ $index }}][assigned_role]" class="form-control form-control-sm" value="{{ $row['assigned_role'] ?? '' }}" placeholder="{{ __('portal.support.fallback_role_placeholder') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">{{ __('portal.support.responsible_users') }}</label>
                            <select name="topics[{{ $index }}][responsible_user_ids][]" class="form-select form-select-sm" multiple size="3">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(in_array($user->id, $row['responsible_user_ids'] ?? [], true))>
                                        {{ $user->displayName() }} @if($user->role?->label) · {{ $user->role->label }} @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('portal.support.responsible_users_hint') }}</div>
                        </div>
                    </div>
                </details>
            @empty
                <div class="portal-empty">{{ __('portal.empty') }}</div>
            @endforelse
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="portal-soft small">{{ __('portal.support.settings_buttons_hint') }}</div>
        <button type="submit" class="btn btn-primary">
            {{ __('portal.save') }}
        </button>
    </div>
</form>

<template id="support-topic-row-template">
    <details class="portal-surface p-3" data-topic-row open>
        <summary class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <div class="fw-semibold">{{ __('portal.support.topic') }} #__INDEX_LABEL__</div>
                <div class="small portal-soft">{{ __('portal.support.new_topic') }}</div>
            </div>
            <label class="form-check form-switch m-0">
                <input type="checkbox" class="form-check-input" name="topics[__INDEX__][is_active]" value="1" checked>
                <span class="form-check-label small">{{ __('portal.active') }}</span>
            </label>
        </summary>

        <input type="hidden" name="topics[__INDEX__][id]" value="">

        <div class="row g-2 mt-3">
            <div class="col-lg-5">
                <label class="form-label mb-1">{{ __('portal.name') }}</label>
                <input type="text" name="topics[__INDEX__][label]" class="form-control form-control-sm" value="" placeholder="{{ __('portal.support.topic_name_placeholder') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label mb-1">{{ __('portal.support.chat_id') }}</label>
                <input type="text" name="topics[__INDEX__][telegram_chat_id]" class="form-control form-control-sm" value="" placeholder="{{ __('portal.support.chat_id_placeholder') }}">
            </div>
            <div class="col-lg-1">
                <label class="form-label mb-1">{{ __('portal.sort_order') }}</label>
                <input type="number" name="topics[__INDEX__][sort_order]" class="form-control form-control-sm" min="0" value="0">
            </div>
            <div class="col-lg-2">
                <label class="form-label mb-1">{{ __('portal.support.fallback_role') }}</label>
                <input type="text" name="topics[__INDEX__][assigned_role]" class="form-control form-control-sm" value="" placeholder="{{ __('portal.support.fallback_role_placeholder') }}">
            </div>
            <div class="col-12">
                <label class="form-label mb-1">{{ __('portal.support.responsible_users') }}</label>
                <select name="topics[__INDEX__][responsible_user_ids][]" class="form-select form-select-sm" multiple size="3">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->displayName() }} @if($user->role?->label) · {{ $user->role->label }} @endif</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('portal.support.responsible_users_hint') }}</div>
            </div>
        </div>
    </details>
</template>

@push('scripts')
<script>
    (function () {
        const addButton = document.getElementById('support-topic-add');
        const rowsWrap = document.getElementById('support-topic-rows');
        const template = document.getElementById('support-topic-row-template');
        if (!addButton || !rowsWrap || !template) return;

        let nextIndex = rowsWrap.querySelectorAll('[data-topic-row]').length;

        addButton.addEventListener('click', () => {
            const html = template.innerHTML
                .replaceAll('__INDEX__', String(nextIndex))
                .replaceAll('__INDEX_LABEL__', String(nextIndex + 1));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;
            if (!row) return;
            rowsWrap.appendChild(row);
            nextIndex += 1;
        });
    })();
</script>
@endpush
@endsection
