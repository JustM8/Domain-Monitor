<div class="col-12">
    @can('sites.control')
    <label class="form-label" for="site-operation-mode">Режим роботи</label>
    <select id="site-operation-mode" name="remote_control_enabled" class="form-select" @disabled(isset($site) && ! auth()->user()->canPortal('sites.write'))>
        <option value="0" @selected(! old('remote_control_enabled', $site->remote_control_enabled ?? false))>Лише моніторинг</option>
        <option value="1" @selected(old('remote_control_enabled', $site->remote_control_enabled ?? false))>Моніторинг і керування</option>
    </select>
    @else
    <div class="fw-semibold">Режим: {{ ($site->remote_control_enabled ?? false) ? 'Моніторинг і керування' : 'Лише моніторинг' }}</div>
    @endcan
    <div class="small portal-soft mt-1">Для звичайних перевірок інтеграція на сайті не потрібна. Керування дозволяє надсилати команди підключеному дочірньому сайту. Перед від’єднанням вимкненого сайту підтвердьте його ввімкнення.</div>
</div>
