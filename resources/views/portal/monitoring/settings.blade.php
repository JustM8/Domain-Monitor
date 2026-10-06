@extends('layouts.portal')
@section('content')
<h1>Monitoring → Settings</h1>
<form method="POST" action="{{ route('portal.monitoring.settings.save') }}">@csrf
<fieldset @disabled(! auth()->user()->canPortal('monitoring.write'))><div class="row g-3">
@foreach(\App\Modules\Monitoring\Services\MonitoringSettings::DEFAULTS as $key => $default)
<div class="col-md-4"><label class="w-100">{{ str_replace('_',' ', $key) }}<input type="number" class="form-control" required name="{{ $key }}" value="{{ old($key, $settings->$key) }}"></label></div>
@endforeach
<div class="col-12"><label class="w-100">TCP allowed ports (Admin only)<input class="form-control" name="tcp_allowed_ports" value="{{ old('tcp_allowed_ports', implode(',', json_decode($settings->tcp_allowed_ports ?? '[80,443]',true))) }}" @readonly(! auth()->user()->isAdmin())></label></div>
<div class="col-12">Default responsible users<br>@foreach($users as $u)<label class="me-3"><input type="checkbox" name="recipient_ids[]" value="{{ $u->id }}" @checked(in_array($u->id, old('recipient_ids', $selectedRecipients)))> {{ $u->name }}</label>@endforeach</div>
</div><button class="btn btn-primary mt-3">Зберегти</button></fieldset></form>
<p class="mt-3">UTC storage; Calendar Europe/Kyiv. Monthly rollups зберігаються довгостроково. Diagnostics hard caps: 32/day, 448/Monitor; failed ≤8/day, manual ≤20/day. Routine successes не створюють raw stream.</p>
@endsection
