@php
    $selectedCompanyId = old('company_id', $client->company_id);
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        <label class="form-label">ПІБ клієнта</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="full_name" class="form-control" value="{{ old('full_name', $client->full_name) }}">
    </div>
    <div class="col-lg-6">
        <label class="form-label">Компанія клієнта</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="company_name" class="form-control" value="{{ old('company_name', $client->company_name) }}" placeholder="Наприклад: Смарт Груп">
    </div>

    <div class="col-lg-6">
        <label class="form-label">{{ __('portal.company') }} (валідація)</label>
        <select @disabled(! auth()->user()->canPortal('support.write')) name="company_id" class="form-select">
            <option value="">{{ __('portal.empty') }}</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Якщо компанія вже є в списку, прив'яжіть її тут.</div>
    </div>

    <div class="col-lg-4">
        <label class="form-label">Telegram ID користувача</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="telegram_user_id" class="form-control" value="{{ old('telegram_user_id', $client->telegram_user_id) }}" required>
    </div>
    <div class="col-lg-4">
        <label class="form-label">Telegram chat ID</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="telegram_chat_id" class="form-control" value="{{ old('telegram_chat_id', $client->telegram_chat_id) }}" required>
    </div>
    <div class="col-lg-4">
        <label class="form-label" title="Службовий стан, який показує, на якому кроці бот зараз працює з клієнтом. Його змінює сама система, вручну краще не чіпати.">
            {{ __('portal.support.state') }}
        </label>
        <div class="d-flex flex-column gap-2">
            <span class="badge rounded-pill text-bg-secondary align-self-start px-3 py-2" title="Службовий стан, який показує, на якому кроці бот зараз працює з клієнтом. Його змінює сама система, вручну краще не чіпати.">
                Бот працює на кроці: {{ $client->stateLabel() }}
            </span>
            <div class="form-text">Службове поле тільки для перегляду. Воно потрібне боту, щоб знати наступний крок з клієнтом.</div>
        </div>
    </div>

    <div class="col-lg-4">
        <label class="form-label">Telegram username</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="telegram_username" class="form-control" value="{{ old('telegram_username', $client->telegram_username) }}" placeholder="@username">
    </div>
    <div class="col-lg-4">
        <label class="form-label">Ім'я в Telegram</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="telegram_first_name" class="form-control" value="{{ old('telegram_first_name', $client->telegram_first_name) }}">
    </div>
    <div class="col-lg-4">
        <label class="form-label">Прізвище в Telegram</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="telegram_last_name" class="form-control" value="{{ old('telegram_last_name', $client->telegram_last_name) }}">
    </div>

    <div class="col-lg-4">
        <label class="form-label">Email</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="email" class="form-control" value="{{ old('email', $client->email) }}">
    </div>
    <div class="col-lg-4">
        <label class="form-label">Телефон</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="phone" class="form-control" value="{{ old('phone', $client->phone) }}">
    </div>
    <div class="col-lg-4">
        <label class="form-label">Посада</label>
        <input @readonly(! auth()->user()->canPortal('support.write')) name="position" class="form-control" value="{{ old('position', $client->position) }}">
    </div>

    <div class="col-12">
        <label class="form-label">Опис клієнта</label>
        <textarea @readonly(! auth()->user()->canPortal('support.write')) name="description" class="form-control" rows="5" placeholder="Короткі примітки про клієнта, нюанси чи домовленості">{{ old('description', $client->description) }}</textarea>
    </div>
</div>
