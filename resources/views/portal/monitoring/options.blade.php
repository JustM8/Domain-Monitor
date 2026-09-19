<fieldset class="col-12" @disabled($monitoringReadOnly ?? false)>
    <legend class="h6">Автоматичний моніторинг</legend>
    <div class="row g-3">
        <div class="col-md-6">
            <input type="hidden" name="monitoring_enabled" value="0">
            <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="monitoring_enabled" value="1" @checked(old('monitoring_enabled', isset($site) ? $site->monitoring_enabled : true))><span class="form-check-label">Перевіряти через cron</span></label>
            <div class="small portal-soft">Працює для Prod і Dev незалежно від дозволу керування. Вимкнення опції призупиняє перевірки.</div>
        </div>
        <div class="col-md-2"><label class="form-label">Інтервал, хв<input type="number" class="form-control" name="monitoring_interval" min="1" max="60" required value="{{ old('monitoring_interval', $site->monitoring_interval ?? 5) }}"></label></div>
        <div class="col-md-2"><label class="form-label">Тайм-аут, с<input type="number" class="form-control" name="monitoring_timeout" min="2" max="10" required value="{{ old('monitoring_timeout', $site->monitoring_timeout ?? 6) }}"></label></div>
        <div class="col-md-2"><label class="form-label">Невдач до сигналу<input type="number" class="form-control" name="monitoring_failure_threshold" min="1" max="5" required value="{{ old('monitoring_failure_threshold', $site->monitoring_failure_threshold ?? 2) }}"></label></div>
        <div class="col-md-6"><label class="form-label w-100">Адреса перевірки (необов’язково)<input type="url" class="form-control" name="monitoring_url" maxlength="255" placeholder="За замовчуванням — адреса сайту" value="{{ old('monitoring_url', $site->monitoring_url ?? '') }}"></label></div>
        <div class="col-md-3"><label class="form-label w-100">Очікувані HTTP-коди<input class="form-control" name="monitoring_status_codes" maxlength="100" placeholder="Порожньо — усі 2xx" value="{{ old('monitoring_status_codes', implode(',', $site->monitoring_status_codes ?? [])) }}"></label></div>
        <div class="col-md-3"><label class="form-label w-100">Очікуваний текст<input class="form-control" name="monitoring_content" maxlength="255" placeholder="Необов’язково" value="{{ old('monitoring_content', $site->monitoring_content ?? '') }}"></label></div>
        <div class="col-12 small portal-soft">Після невдачі підтверджувальна перевірка запланована через 1 хв. Фактичний час залежить від cron та черги сайтів. Ручна перевірка не змінює автоматичні інциденти.</div>
    </div>
</fieldset>
