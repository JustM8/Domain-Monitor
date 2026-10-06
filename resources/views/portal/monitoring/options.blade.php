<fieldset class="col-12" @disabled($monitoringReadOnly ?? false)>
    <legend class="h6">Monitoring V2 — Primary HTTP</legend>
    <input type="hidden" name="monitoring_enabled" value="0">
    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="monitoring_enabled" value="1" @checked(old('monitoring_enabled', isset($site) ? $site->monitoring_enabled : true))><span class="form-check-label">Monitoring</span></label>
    @if(isset($site))
        @php($primaryMonitor = $site->primaryMonitor()->first())
        @if($primaryMonitor)
            @php($monitorState = \Illuminate\Support\Facades\DB::table('monitoring_states')->where('monitor_id', $primaryMonitor->id)->first())
            <div>Primary: {{ strtoupper(\App\Modules\Monitoring\Services\MonitorHistory::availability($monitorState)) }}</div>
            @can('monitoring.read')<a href="{{ route('portal.monitoring.show', $primaryMonitor->id) }}">Параметри, інциденти, звіт</a>@endcan
        @endif
    @endif
    <div class="small portal-soft">Prod і Dev незалежно від керування. Primary HTTP успадковує глобальний інтервал; параметри та додаткові monitors — у розділі Monitoring.</div>
</fieldset>
