<?php

namespace App\Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\Status;
use App\Modules\Site\Models\Site;
use App\Modules\Site\Models\SiteRevision;
use App\Services\SiteSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
            'url' => ['required', 'string', 'max:255'],
            'site_type' => ['required', 'in:site,3d,devbase'],
            'environment' => ['required', 'in:prod,dev'],
            'admin_url' => ['nullable', 'string', 'max:255'],
            'admin_login' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'repo_url' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'cms' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'ssl' => ['nullable', 'boolean'],
            'remote_control_enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $adminPassword = trim((string) $request->input('admin_password'));
        $data['admin_password'] = $adminPassword !== '' ? Crypt::encryptString($adminPassword) : null;
        $data['api_token'] = Crypt::encryptString(Str::random(60));
        $data['ssl'] = $request->boolean('ssl');
        $data['remote_control_enabled'] = $request->boolean('remote_control_enabled');
        $data['is_active'] = true;

        $site = Site::create($data);

        $this->logRevision($site, 'create', [], $site->toArray());
        $this->logActivity($site, 'site.created', $site->toArray());
        $sync = $this->syncRemoteControlIfEnabled($site, 'active');

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
            'apiToken' => $site->api_token ? Crypt::decryptString($site->api_token) : null,
            'siteTypeOptions' => Site::siteTypeOptions(),
            'environmentOptions' => Site::environmentOptions(),
            'adminPassword' => $site->decryptedAdminPassword(),
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
            'url' => ['required', 'string', 'max:255'],
            'site_type' => ['required', 'in:site,3d,devbase'],
            'environment' => ['required', 'in:prod,dev'],
            'admin_url' => ['nullable', 'string', 'max:255'],
            'admin_login' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'repo_url' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'cms' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'ssl' => ['nullable', 'boolean'],
            'remote_control_enabled' => ['nullable', 'boolean'],
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
        $data['remote_control_enabled'] = $request->boolean('remote_control_enabled');
        $site->update($data);

        $this->logRevision($site, 'update', $before, $site->fresh()->toArray());
        $this->logActivity($site, 'site.updated', ['before' => $before, 'after' => $site->fresh()->toArray()]);
        $sync = $this->syncRemoteControlIfEnabled($site->fresh(), $site->is_active ? 'active' : 'disabled', [
            'reason' => $site->disabled_reason,
        ]);

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

    public function check(Site $site)
    {
        $url = trim((string) $site->url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return back()->with('error', __('portal.invalid_url'));
        }

        try {
            $response = Http::timeout(15)->retry(1, 200)->get($url);
        } catch (\Throwable $e) {
            $this->logActivity($site, 'site.check_failed', ['error' => $e->getMessage()]);

            return back()->with('error', __('portal.check_failed'));
        }

        $statusCode = $response->status();
        $this->logActivity($site, 'site.checked', ['status' => $statusCode, 'url' => $url]);

        if ($statusCode === 200) {
            return back()->with('success', __('portal.check_ok'));
        }

        return back()->with('error', __('portal.check_bad_status', ['status' => $statusCode]));
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
        $data = $request->validate([
            'disabled_reason' => ['required', 'string', 'max:2000'],
        ]);

        $before = $site->toArray();
        $site->update([
            'is_active' => false,
            'disabled_reason' => $data['disabled_reason'],
            'disabled_by' => auth()->id(),
            'disabled_at' => now(),
        ]);

        $this->logRevision($site, 'disable', $before, $site->fresh()->toArray());
        $this->logActivity($site, 'site.disabled', $data);
        $sync = $this->syncRemoteControlIfEnabled($site->fresh(), 'disabled', [
            'reason' => $data['disabled_reason'],
        ]);

        $response = back()->with('success', __('portal.site_disabled'));

        if ($sync && ! $sync['ok']) {
            $response->with('warning', __('portal.site_sync_failed_with_reason', [
                'reason' => $this->syncFailureReason($sync),
            ]));
        }

        return $response;
    }

    public function enable(Site $site)
    {
        $before = $site->toArray();
        $site->update([
            'is_active' => true,
            'disabled_reason' => null,
            'disabled_by' => null,
            'disabled_at' => null,
        ]);

        $this->logRevision($site, 'enable', $before, $site->fresh()->toArray());
        $this->logActivity($site, 'site.enabled', []);
        $sync = $this->syncRemoteControlIfEnabled($site->fresh(), 'active');

        $response = back()->with('success', __('portal.site_enabled'));

        if ($sync && ! $sync['ok']) {
            $response->with('warning', __('portal.site_sync_failed_with_reason', [
                'reason' => $this->syncFailureReason($sync),
            ]));
        }

        return $response;
    }

    public function toggleRemoteControl(Site $site)
    {
        $site->update([
            'remote_control_enabled' => ! $site->remote_control_enabled,
        ]);

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
