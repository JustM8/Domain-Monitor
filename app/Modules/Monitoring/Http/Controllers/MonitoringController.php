<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Monitoring\Services\MonitoringNotifications;
use App\Modules\Monitoring\Services\MonitoringOptions;
use App\Modules\Monitoring\Services\MonitoringReport;
use App\Modules\Monitoring\Services\SiteMonitor;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Site\Models\Site;
use App\Modules\Site\Services\SiteMetadataService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringNotifications $notifications, MonitoringReport $reports)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'environment' => ['nullable', 'in:prod,dev'],
            'availability' => ['nullable', 'in:up,down,planned,unknown,stale'],
            'enabled' => ['nullable', 'in:0,1'], 'mode' => ['nullable', 'in:0,1'],
        ]);
        $query = Site::query()->leftJoin('monitoring_states as ms', 'ms.site_id', '=', 'sites.id')
            ->select('sites.*', 'ms.availability', 'ms.last_checked_at', 'ms.next_check_at', 'ms.http_status',
                'ms.response_ms', 'ms.consecutive_failures', 'ms.observed_interval');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('sites.name', 'like', '%'.$request->input('search').'%')->orWhere('sites.url', 'like', '%'.$request->input('search').'%'));
        }
        if ($request->filled('environment')) {
            $query->where('sites.environment', $request->input('environment'));
        }
        foreach (['enabled' => 'monitoring_enabled', 'mode' => 'remote_control_enabled'] as $filter => $column) {
            if ($request->filled($filter)) {
                $query->where('sites.'.$column, $request->input($filter));
            }
        }
        $staleIds = DB::table('monitoring_states')->whereNotNull('last_checked_at')->get()
            ->filter(fn ($state) => CarbonImmutable::parse($state->last_checked_at, 'UTC')
                ->addMinutes(($state->observed_interval ?: 5) * 2)->lt(now()))
            ->pluck('site_id')->all();
        if ($request->filled('availability')) {
            if ($request->input('availability') === 'unknown') {
                $query->where(fn ($q) => $q->whereNull('ms.last_checked_at')->orWhereIn('sites.id', $staleIds));
            } elseif ($request->input('availability') === 'stale') {
                $query->where('sites.monitoring_enabled', true)->where('ms.next_check_at', '<', now()->subMinute());
            } else {
                $query->where('ms.availability', $request->input('availability'))->whereNotIn('sites.id', $staleIds);
            }
        }
        $ids = (clone $query)->pluck('sites.id')->all();
        $range = $reports->range($request, $ids);
        $dueQuery = Site::query()->where('monitoring_enabled', true)
            ->leftJoin('monitoring_states as ms', 'ms.site_id', '=', 'sites.id')
            ->where(fn ($q) => $q->whereNull('ms.next_check_at')->orWhere('ms.next_check_at', '<=', now()));

        return view('portal.monitoring.index', [
            'sites' => $query->orderBy('sites.name')->paginate(30)->withQueryString(),
            'report' => $reports->build($ids, $range),
            'lastRun' => DB::table('monitoring_runs')->latest('id')->first(),
            'enabledSitesCount' => Site::query()->where('monitoring_enabled', true)->count(),
            'dueSitesCount' => $dueQuery->count('sites.id'),
            'pendingNotifications' => DB::table('monitoring_notifications')->whereNull('sent_at')->whereNull('cancelled_at')->count(),
            'recipients' => $this->recipients(), 'selectedRecipients' => $notifications->recipientIds(),
        ]);
    }

    public function show(Request $request, Site $site, MonitoringReport $reports)
    {
        $range = $reports->range($request, [$site->id]);
        $checks = DB::table('monitoring_checks')->where('site_id', $site->id)
            ->where('checked_at', '>=', $range['from']->utc())->where('checked_at', '<=', $range['to']->utc())
            ->latest('id')->paginate(30, ['*'], 'checks_page')->withQueryString();
        $incidents = DB::table('monitoring_incidents')->where('site_id', $site->id)
            ->where('opened_at', '<', $range['to']->utc())
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>', $range['from']->utc()))
            ->latest('id')->paginate(20, ['*'], 'incidents_page')->withQueryString();

        return view('portal.monitoring.show', [
            'site' => $site, 'state' => DB::table('monitoring_states')->where('site_id', $site->id)->first(),
            'checks' => $checks, 'incidents' => $incidents, 'report' => $reports->build([$site->id], $range),
            'recipients' => $this->recipients(),
            'latestCheck' => DB::table('monitoring_checks')->where('site_id', $site->id)->latest('id')->first(),
        ]);
    }

    public function updateSite(Request $request, Site $site, MonitoringOptions $options, SiteMetadataService $metadata)
    {
        $data = $options->fromRequest($request);
        // No checkboxes selected means use global recipients, only in this full settings form.
        $data['monitoring_recipient_ids'] ??= null;
        $before = $site->only(array_keys($data));
        $site = $metadata->update($site, $data);
        $log = new ActivityLog(['user_id' => auth()->id(), 'action' => 'monitoring.site_settings_updated',
            'properties' => ['before' => $before, 'after' => $site->only(array_keys($data))]]);
        $log->subject()->associate($site);
        $log->save();

        return back()->with('success', 'Налаштування моніторингу збережено.');
    }

    public function check(Site $site, SiteMonitor $monitor)
    {
        try {
            $result = $monitor->check($site, true);
        } catch (\App\Modules\Monitoring\Services\CheckAlreadyRunning $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with($result['availability'] === 'down' ? 'warning' : 'success',
            'Ручна перевірка: '.($result['error'] ?: ['up' => 'доступний', 'planned' => 'планово вимкнений'][$result['availability']]));
    }

    private function recipients()
    {
        return User::with('role')->where('is_active', true)->get()
            ->filter(fn ($u) => $u->canPortal('monitoring.read') && $u->canUseAccessBot() && $u->telegramIsLinked());
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
