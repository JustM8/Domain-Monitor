@php($c = $monitor?->config ?? [])
<div class="row g-3 mt-2">
<div class="col-md-6"><label class="form-label w-100">Name<input class="form-control" name="name" required maxlength="255" value="{{ old('name', $monitor?->name) }}"></label></div>
<div class="col-md-3"><label>Type<select class="form-select" name="type" @disabled($monitor)>@foreach(['http','tcp','heartbeat'] as $t)<option @selected(($monitor?->type ?? old('type')) === $t)>{{ $t }}</option>@endforeach</select></label></div>
<div class="col-md-3"><input type="hidden" name="enabled" value="0"><label><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $monitor?->enabled ?? true))> Enabled</label></div>
@foreach(['custom_interval_seconds' => 'Custom interval sec (empty = global)', 'timeout_seconds' => 'Timeout sec (empty = global)', 'failure_threshold' => 'Failure threshold (empty = global)', 'recovery_threshold' => 'Recovery threshold (empty = global)'] as $key => $label)
<div class="col-md-3"><label class="w-100">{{ $label }}<input class="form-control" type="number" name="{{ $key }}" value="{{ old($key, $monitor?->$key) }}"></label></div>
@endforeach
<div class="col-12 small">Заповніть поля відповідного типу. Heartbeat використовує власний job interval.</div>
<div class="col-md-6"><label class="w-100">HTTP URL (empty = Site URL)<input class="form-control" type="url" name="url" value="{{ old('url', $c['url'] ?? '') }}"></label></div>
<div class="col-md-3"><label>HTTP statuses<input class="form-control" name="status_codes" placeholder="200,204" value="{{ old('status_codes', implode(',', $c['status_codes'] ?? [])) }}"></label></div>
<div class="col-md-3"><label>HTTP content<input class="form-control" name="content" maxlength="255" value="{{ old('content', $c['content'] ?? '') }}"></label></div>
<div class="col-12"><label><input type="checkbox" name="respect_site_control" value="1" @checked(old('respect_site_control', $c['respect_site_control'] ?? false))> Враховувати підтверджене вимкнення Site для follow-Site HTTP</label></div>
<div class="col-md-6"><label>TCP hostname<input class="form-control" name="hostname" value="{{ old('hostname', $c['hostname'] ?? '') }}"></label></div><div class="col-md-6"><label>TCP port<input class="form-control" type="number" name="port" value="{{ old('port', $c['port'] ?? 443) }}"></label></div>
<div class="col-md-6"><label>Heartbeat job interval sec<input class="form-control" type="number" name="expected_interval_seconds" value="{{ old('expected_interval_seconds', $c['expected_interval_seconds'] ?? 86400) }}"></label></div><div class="col-md-6"><label>Heartbeat grace sec<input class="form-control" type="number" name="grace_seconds" value="{{ old('grace_seconds', $c['grace_seconds'] ?? 300) }}"></label></div>
<div class="col-12"><label>Recipients<select class="form-select" name="recipient_mode">@foreach(['inherit','explicit','mute'] as $mode)<option @selected(old('recipient_mode', $monitor?->recipient_mode ?? 'inherit') === $mode)>{{ $mode }}</option>@endforeach</select></label><div class="small">Explicit без вибраних користувачів = порожній список.</div>
@foreach($users as $u)<label class="me-3"><input type="checkbox" name="recipient_ids[]" value="{{ $u->id }}" @checked(in_array($u->id, old('recipient_ids', $selectedRecipients)))> {{ $u->name }}</label>@endforeach</div>
</div>
