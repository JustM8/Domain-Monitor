<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Monitoring\Models\Monitor;
use App\Modules\Monitoring\Services\MonitorHeartbeat;
use App\Modules\Monitoring\Services\MonitorHistory;
use App\Modules\Monitoring\Services\MonitoringSettings;
use App\Modules\Monitoring\Services\MonitoringTime;
use App\Modules\Monitoring\Services\MonitorManager;
use App\Modules\Monitoring\Services\MonitorRunner;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    private function users()
    {
        return User::with('role')->get()->filter(fn ($u) => $u->canPortal('monitoring.read') && $u->canUseAccessBot() && $u->telegramIsLinked());
    }

    public function index(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:255', 'type' => 'nullable|in:http,tcp,heartbeat']);
        $q = Monitor::with('site')->whereHas('site');
        if ($r->filled('search')) {
            $q->where('name', 'like', '%'.$r->input('search').'%');
        }
        if ($r->filled('type')) {
            $q->where('type', $r->input('type'));
        }
        $states = DB::table('monitoring_states')->get()->keyBy('monitor_id');
        $totals = ['total' => 0, 'up' => 0, 'down' => 0, 'unknown' => 0, 'planned' => 0, 'heartbeat_misses' => 0];
        foreach (Monitor::whereHas('site')->get() as $m) {
            $state = $states[$m->id];
            $status = $m->enabled ? MonitorHistory::availability($state) : 'unknown';
            $totals['total']++;
            $totals[$status]++;
            if ($m->type === 'heartbeat' && $status === 'down') {
                $totals['heartbeat_misses']++;
            }
        }

        return view('portal.monitoring.index', ['monitors' => $q->orderBy('site_id')->orderBy('id')->paginate(30)->withQueryString(), 'states' => $states, 'totals' => $totals,
            'incidents' => DB::table('monitoring_incidents')->whereNull('closed_at')->count(), 'sslWarnings' => DB::table('monitoring_certificates')->where('not_after', '<=', MonitoringTime::store(MonitoringTime::now()->addDays(app(MonitoringSettings::class)->get()->ssl_warning_days)))->count(),
            'lastRun' => DB::table('monitoring_runs')->latest('id')->first(), 'sites' => Site::orderBy('name')->get(), 'users' => $this->users()]);
    }

    public function show(Monitor $monitor, MonitorHistory $history)
    {
        abort_unless($monitor->site, 404);

        return view('portal.monitoring.show', ['monitor' => $monitor, 'state' => DB::table('monitoring_states')->where('monitor_id', $monitor->id)->first(),
            'incidents' => DB::table('monitoring_incidents')->where('monitor_id', $monitor->id)->latest('id')->limit(10)->get(), 'diagnostics' => DB::table('monitoring_diagnostics')->where('monitor_id', $monitor->id)->latest('id')->limit(20)->get(),
            'rollups' => $history->report($monitor->id), 'certificate' => DB::table('monitoring_certificates')->where('monitor_id', $monitor->id)->latest('verified_at')->first(), 'users' => $this->users(),
            'selectedRecipients' => DB::table('monitoring_monitor_recipients')->where('monitor_id', $monitor->id)->pluck('user_id')->all(), 'zone' => MonitoringTime::zone($monitor->site_id)]);
    }

    private function input(Request $r, ?Monitor $m = null): array
    {
        $type = $m?->type ?? $r->input('type');

        return ['name' => $r->input('name'), 'type' => $type, 'enabled' => $r->boolean('enabled'), 'custom_interval_seconds' => $r->input('custom_interval_seconds'),
            'timeout_seconds' => $r->input('timeout_seconds'), 'failure_threshold' => $r->input('failure_threshold'), 'recovery_threshold' => $r->input('recovery_threshold'),
            'recipient_mode' => $r->input('recipient_mode', 'inherit'), 'recipient_ids' => $r->input('recipient_ids', []),
            'config' => match ($type) {
                'http' => ['url' => $r->input('url'), 'status_codes' => $r->filled('status_codes') ? array_map('intval', explode(',', $r->input('status_codes'))) : null, 'content' => $r->input('content'), 'respect_site_control' => $r->boolean('respect_site_control')],
                'tcp' => ['hostname' => $r->input('hostname'), 'port' => $r->input('port')],
                'heartbeat' => ['expected_interval_seconds' => $r->input('expected_interval_seconds'), 'grace_seconds' => $r->input('grace_seconds')], default => [],
            }];
    }

    public function store(Request $r, MonitorManager $manager)
    {
        $r->validate(['site_id' => 'required|exists:sites,id']);
        $site = Site::findOrFail($r->input('site_id'));
        $monitor = $manager->create($site->id, $this->input($r));

        return redirect()->route('portal.monitoring.show', $monitor->id);
    }

    public function update(Request $r, Monitor $monitor, MonitorManager $manager)
    {
        abort_unless($monitor->site, 404);
        $data = $this->input($r, $monitor);
        $data['slot'] = $monitor->slot;
        $manager->update($monitor->id, $data);

        return back()->with('success', 'Збережено. Нове спостереження починається UNKNOWN.');
    }

    public function check(Monitor $monitor, MonitorRunner $runner)
    {
        abort_unless($monitor->site && $monitor->type !== 'heartbeat', 422);
        try {
            $r = $runner->manual($monitor->id);
        } catch (\RuntimeException) {
            return back()->with('warning', 'Ліміт ручних перевірок досягнуто.');
        }

        return back()->with($r['up'] ? 'success' : 'warning', 'Manual: '.($r['error_kind'] ?? 'UP').($r['persisted'] ? '' : ' (quota: detail не збережено)'));
    }

    public function settings()
    {
        return view('portal.monitoring.settings', ['settings' => app(MonitoringSettings::class)->get(), 'users' => $this->users(), 'selectedRecipients' => DB::table('monitoring_default_recipients')->pluck('user_id')->all()]);
    }

    public function saveSettings(Request $r, MonitoringSettings $settings)
    {
        $data = $r->all();
        $data['tcp_allowed_ports'] = array_map('intval', explode(',', (string) $r->input('tcp_allowed_ports', '80,443')));
        // The broad PM write permission does not confer permission to open scanning ports.
        if (! $r->user()->isAdmin()) {
            abort_unless($data['tcp_allowed_ports'] === $settings->ports(), 403);
        }
        $settings->update($data);

        return back()->with('success', 'Monitoring Settings збережено.');
    }

    public function credential(Request $r, Monitor $monitor, MonitorHeartbeat $heartbeats)
    {
        abort_unless($monitor->type === 'heartbeat' && $monitor->site, 422);
        $r->validate(['overlap_seconds' => 'nullable|integer|between:0,3600']);
        if ($r->boolean('revoke')) {
            $heartbeats->revoke($monitor->id);

            return back()->with('success', 'Credentials revoked.');
        }

        return back()->with('heartbeatCredential', $heartbeats->rotate($monitor->id, $r->integer('overlap_seconds')));
    }

    public function preference(Request $r, Monitor $monitor)
    {
        $r->validate(['display_timezone' => 'nullable|timezone']);
        if ($r->filled('display_timezone')) {
            DB::table('monitoring_site_preferences')->updateOrInsert(['site_id' => $monitor->site_id], ['display_timezone' => $r->input('display_timezone')]);
        } else {
            DB::table('monitoring_site_preferences')->where('site_id', $monitor->site_id)->delete();
        }

        return back();
    }

    public function site(Site $site)
    {
        return redirect()->route('portal.monitoring.show', $site->primaryMonitor()->firstOrFail()->id);
    }
}
