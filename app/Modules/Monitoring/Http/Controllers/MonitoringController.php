<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Monitoring\Services\MonitoringNotifications;
use App\Modules\Monitoring\Services\SiteMonitor;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringNotifications $notifications)
    {
        $query = Site::query()->leftJoin('monitoring_states as ms', 'ms.site_id', '=', 'sites.id')
            ->select('sites.*', 'ms.availability', 'ms.last_checked_at', 'ms.next_check_at', 'ms.http_status', 'ms.response_ms', 'ms.consecutive_failures');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('sites.name', 'like', '%'.$request->input('search').'%')->orWhere('sites.url', 'like', '%'.$request->input('search').'%'));
        }
        if ($request->filled('environment')) {
            $query->where('sites.environment', $request->input('environment'));
        }
        if ($request->filled('availability')) {
            $request->input('availability') === 'unknown' ? $query->whereNull('ms.last_checked_at') : $query->where('ms.availability', $request->input('availability'));
        }
        $summary = Site::query()->selectRaw('environment, count(*) as total')->groupBy('environment')->pluck('total', 'environment');
        $availability = Site::query()->join('monitoring_states as ms', 'ms.site_id', '=', 'sites.id')->where('environment', 'prod')->selectRaw('ms.availability, count(*) as total')->groupBy('ms.availability')->pluck('total', 'availability');
        $daily = DB::table('monitoring_daily')->join('sites', 'sites.id', '=', 'monitoring_daily.site_id')->whereNull('sites.deleted_at')
            ->where('sites.environment', 'prod')->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('SUM(checks - planned) as total, SUM(successful) as successful')->first();

        return view('portal.monitoring.index', [
            'sites' => $query->orderBy('sites.name')->paginate(30)->withQueryString(), 'summary' => $summary, 'availability' => $availability,
            'uptime' => $daily->total ? round(100 * $daily->successful / $daily->total, 2) : null,
            'lastRun' => DB::table('monitoring_runs')->latest('id')->first(),
            'openIncidents' => DB::table('monitoring_incidents')->join('sites', 'sites.id', '=', 'monitoring_incidents.site_id')->whereNull('sites.deleted_at')->whereNull('closed_at')->count(),
            'pendingNotifications' => DB::table('monitoring_notifications')->whereNull('sent_at')->whereNull('cancelled_at')->count(),
            'recipients' => User::with('role')->where('is_active', true)->get()->filter(fn ($u) => $u->canPortal('monitoring.read') && $u->canUseAccessBot() && $u->telegramIsLinked()),
            'selectedRecipients' => $notifications->recipientIds(),
        ]);
    }

    public function show(Site $site)
    {
        return view('portal.monitoring.show', [
            'site' => $site, 'state' => DB::table('monitoring_states')->where('site_id', $site->id)->first(),
            'checks' => DB::table('monitoring_checks')->where('site_id', $site->id)->latest('id')->paginate(30),
            'incidents' => DB::table('monitoring_incidents')->where('site_id', $site->id)->latest('id')->limit(30)->get(),
            'daily' => DB::table('monitoring_daily')->where('site_id', $site->id)->orderByDesc('day')->limit(30)->get(),
        ]);
    }

    public function check(Site $site, SiteMonitor $monitor)
    {
        try {
            $result = $monitor->check($site, true);
        } catch (\App\Modules\Monitoring\Services\CheckAlreadyRunning $e) {
            return back()->with('warning', 'Перевірка вже виконується.');
        }

        return back()->with($result['availability'] === 'down' ? 'warning' : 'success', 'Результат: '.($result['error'] ?: $result['availability']));
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['recipient_ids' => ['nullable', 'array', 'max:100'], 'recipient_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')]]);
        $ids = array_map('intval', $data['recipient_ids'] ?? []);
        foreach (User::with('role')->whereIn('id', $ids)->get() as $user) {
            abort_unless($user->canPortal('monitoring.read') && $user->canUseAccessBot() && $user->telegramIsLinked(), 422, 'Одержувач має бути активним Admin/PM з підключеним Access-ботом.');
        }
        DB::table('monitoring_settings')->updateOrInsert(['id' => 1], ['recipient_ids' => json_encode($ids), 'updated_at' => now(), 'created_at' => now()]);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'monitoring.recipients_updated', 'properties' => ['recipient_ids' => $ids]]);

        return back()->with('success', 'Відповідальних збережено.');
    }
}
