<?php

namespace App\Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\Status;
use App\Modules\Site\Models\Site;
use App\Modules\Site\Models\SiteRevision;
use App\Modules\Site\Services\SiteSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $query = Site::with(['status', 'company', 'ftpAccounts.sites', 'hostingAccounts.hosting'])->latest();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('admin_url', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->integer('status_id'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('site_type')) {
            $query->where('site_type', $request->string('site_type')->value());
        }

        if ($request->filled('display_mode')) {
            $query->where('display_mode', $request->input('display_mode'));
        }

        if ($request->filled('environment')) {
            $query->where('environment', $request->string('environment')->value());
        }

        if ($request->boolean('only_disabled')) {
            $query->where('is_active', false);
        }

        return view('portal.sites.index', [
            'sites' => $query->paginate(20)->withQueryString(),
            'trashedSites' => Site::onlyTrashed()->with(['status', 'company'])->latest('deleted_at')->limit(20)->get(),
            'statuses' => Status::orderBy('sort_order')->get(),
            'companies' => Company::orderBy('name')->get(),
            'siteTypeOptions' => Site::siteTypeOptions(),
            'environmentOptions' => Site::environmentOptions(),
        ]);
    }

    public function create()
    {
        return redirect()->route('portal.sites.add');
    }

    public function add()
    {
        return view('portal.sites.add', [
            'statuses' => Status::orderBy('sort_order')->get(),
            'companies' => Company::orderBy('name')->get(),
            'siteTypeOptions' => Site::siteTypeOptions(),
            'environmentOptions' => Site::environmentOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'site_type' => ['required', 'in:site,3d,devbase'],
            'environment' => ['required', 'in:prod,dev'],
            'admin_url' => ['nullable', 'url:http,https', 'max:255'],
            'admin_login' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'repo_url' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'cms' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'ssl' => ['nullable', 'boolean'],
            'remote_control_enabled' => [\Illuminate\Validation\Rule::prohibitedIf(! $request->user()->canPortal('sites.control')), 'nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $adminPassword = trim((string) $request->input('admin_password'));
        $data['admin_password'] = $adminPassword !== '' ? Crypt::encryptString($adminPassword) : null;
        $data['api_token'] = Crypt::encryptString(Str::random(60));
        $data['ssl'] = $request->boolean('ssl');
        $data += app(\App\Modules\Site\Services\SitePresentation::class)->fromRequest($request);
        if ($request->user()->canPortal('sites.control')) {
            $data['remote_control_enabled'] = $request->boolean('remote_control_enabled');
        }
        $data['is_active'] = true;

        $site = Site::create($data);

        $this->logRevision($site, 'create', [], $site->toArray());
        $this->logActivity($site, 'site.created', $site->toArray());
        $sync = null;

        $response = redirect()->route('portal.sites.show', $site)->with('success', __('portal.saved'));

        if ($sync && ! $sync['ok']) {
            $response->with('warning', __('portal.site_sync_failed_with_reason', [
                'reason' => $this->syncFailureReason($sync),
            ]));
        }

        return $response;
    }

    public function show(Site $site)
    {
        $site->load([
            'status',
            'company',
            'ftpAccounts.company',
            'ftpAccounts.sites',
            'hostingAccounts.company',
            'hostingAccounts.hosting',
            'hostingAccounts.sites',
            'revisions.changedBy',
        ]);

        return view('portal.sites.show', [
            'site' => $site,
            'statuses' => Status::orderBy('sort_order')->get(),
            'companies' => Company::orderBy('name')->get(),
            'apiToken' => auth()->user()->canPortal('sites.control') && $site->api_token ? Crypt::decryptString($site->api_token) : null,
            'siteTypeOptions' => Site::siteTypeOptions(),
            'environmentOptions' => Site::environmentOptions(),
            'adminPassword' => $site->decryptedAdminPassword(),
            'controlAttempts' => \Illuminate\Support\Facades\DB::table('site_control_attempts as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->where('a.site_id', $site->id)->select('a.*', 'u.name as actor_name')->orderByDesc('a.id')->paginate(20, ['*'], 'attempts_page'),
        ]);
    }

    public function edit(Site $site)
    {
        return redirect()->route('portal.sites.show', $site);
    }

    public function update(Request $request, Site $site)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'site_type' => ['required', 'in:site,3d,devbase'],
            'environment' => ['required', 'in:prod,dev'],
            'admin_url' => ['nullable', 'url:http,https', 'max:255'],
            'admin_login' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'repo_url' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'cms' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'ssl' => ['nullable', 'boolean'],
            'remote_control_enabled' => [\Illuminate\Validation\Rule::prohibitedIf(! $request->user()->canPortal('sites.control')), 'nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $before = $site->toArray();

        $adminPassword = trim((string) $request->input('admin_password'));
        if ($adminPassword !== '') {
            $data['admin_password'] = Crypt::encryptString($adminPassword);
        } else {
            unset($data['admin_password']);
        }

        $data['ssl'] = $request->boolean('ssl');
        $data += app(\App\Modules\Site\Services\SitePresentation::class)->fromRequest($request);
        if ($request->user()->canPortal('sites.control')) {
            $data['remote_control_enabled'] = $request->boolean('remote_control_enabled');
        }
        $site = app(\App\Modules\Site\Services\SiteMetadataService::class)->update($site, $data);

        $this->logRevision($site, 'update', $before, $site->fresh()->toArray());
        $this->logActivity($site, 'site.updated', ['before' => $before, 'after' => $site->fresh()->toArray()]);
        $sync = null;

        $response = redirect()->route('portal.sites.show', $site)->with('success', __('portal.saved'));

        if ($sync && ! $sync['ok']) {
            $response->with('warning', __('portal.site_sync_failed_with_reason', [
                'reason' => $this->syncFailureReason($sync),
            ]));
        }

        return $response;
    }

    public function destroy(Site $site)
    {
        $site->delete();

        $this->logRevision($site, 'delete', $site->toArray(), []);
        $this->logActivity($site, 'site.deleted', $site->toArray());

        return redirect()->route('portal.sites.index')->with('success', __('portal.deleted'));
    }

    public function check(Site $site, \App\Modules\Monitoring\Services\SiteMonitor $monitor)
    {
        try {
            $result = $monitor->check($site, true);
        } catch (\App\Modules\Monitoring\Services\CheckAlreadyRunning $e) {
            return back()->with('warning', 'Перевірка вже виконується.');
        }

        return back()->with($result['availability'] === 'down' ? 'warning' : 'success', 'Результат: '.($result['error'] ?: $result['availability']));
    }

    public function sync(Site $site)
    {
        $result = $this->syncRemoteControlIfEnabled(
            $site,
            $site->is_active ? 'active' : 'disabled',
            ['reason' => $site->disabled_reason]
        );

        if ($result === null) {
            return back()->with('info', __('portal.remote_control_disabled_notice'));
        }

        if ($result['ok']) {
            return back()->with('success', __('portal.site_sync_sent'));
        }

        return back()->with('error', __('portal.site_sync_failed'));
    }

    public function restore(int $site)
    {
        $record = Site::withTrashed()->findOrFail($site);
        $record->restore();

        $this->logActivity($record, 'site.restored', []);

        return back()->with('success', __('portal.restored'));
    }

    public function forceDelete(int $site)
    {
        $record = Site::withTrashed()->findOrFail($site);
        $record->forceDelete();

        return back()->with('success', __('portal.deleted_permanently'));
    }

    public function disable(Request $request, Site $site)
    {
        $data = $request->validate(['disabled_reason' => ['required', 'string', 'max:2000']]);
        $site = app(\App\Modules\Site\Services\SiteControlService::class)->change($site, $request->user(), false, $data['disabled_reason']);
        $result = app(SiteSyncService::class)->sync($site, 'disabled');

        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }

    public function enable(Request $request, Site $site)
    {
        $data = $request->validate(['control_reason' => ['required', 'string', 'max:2000']]);
        $site = app(\App\Modules\Site\Services\SiteControlService::class)->change($site, $request->user(), true, $data['control_reason']);
        $result = app(SiteSyncService::class)->sync($site, 'active');

        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }

    public function toggleRemoteControl(Site $site)
    {
        $site = \Illuminate\Support\Facades\DB::transaction(function () use ($site) {
            $locked = Site::query()->lockForUpdate()->findOrFail($site->id);
            $locked->forceFill(['remote_control_enabled' => ! $locked->remote_control_enabled, 'control_version' => $locked->control_version + 1])->save();
            $this->logActivity($locked, 'site.remote_control_changed', ['enabled' => $locked->remote_control_enabled, 'version' => $locked->control_version]);

            return $locked;
        });

        return back()->with(
            'success',
            $site->remote_control_enabled
                ? __('portal.remote_control_enabled')
                : __('portal.remote_control_disabled')
        );
    }

    protected function logRevision(Site $site, string $type, array $before, array $after): void
    {
        SiteRevision::create([
            'site_id' => $site->id,
            'changed_by' => auth()->id(),
            'change_type' => $type,
            'before_data' => $before,
            'after_data' => $after,
        ]);

        $ids = SiteRevision::query()
            ->where('site_id', $site->id)
            ->latest('id')
            ->pluck('id')
            ->slice(10);

        if ($ids->isNotEmpty()) {
            SiteRevision::query()->whereIn('id', $ids)->delete();
        }
    }

    protected function logActivity(Site $site, string $action, array $properties): void
    {
        $activity = new ActivityLog([
            'user_id' => auth()->id(),
            'action' => $action,
            'properties' => $properties,
        ]);

        $activity->subject()->associate($site);
        $activity->save();
    }

    protected function syncChildSite(Site $site, string $state, array $payload = []): array
    {
        return app(SiteSyncService::class)->sync($site, $state, $payload);
    }

    protected function syncRemoteControlIfEnabled(Site $site, string $state, array $payload = []): ?array
    {
        if (! $site->remote_control_enabled) {
            return null;
        }

        return $this->syncChildSite($site, $state, $payload);
    }

    protected function syncFailureReason(array $sync): string
    {
        $reason = trim((string) data_get($sync, 'message', ''));

        if ($reason === '') {
            $reason = __('portal.site_sync_failed');
        }

        return Str::limit($reason, 140);
    }
}
