<?php

namespace App\Modules\Hosting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class HostingAccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $accounts = HostingAccount::query()
            ->with(['hosting', 'sites', 'company'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('login', 'like', "%{$search}%")
                        ->orWhere('ssh_host', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('hosting', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('sites', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.hosting-accounts.index', [
            'accounts' => $accounts,
            'hostings' => Hosting::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'trashedAccounts' => HostingAccount::onlyTrashed()->with('hosting')->latest('deleted_at')->limit(20)->get(),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.hosting-accounts.add', [
            'hostings' => Hosting::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hosting_id' => ['required', 'exists:hostings,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'ssh_host' => ['nullable', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_login' => ['nullable', 'string', 'max:255'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $siteIds = array_values(array_filter($data['site_ids']));
        $data['company_id'] = $data['company_id'] ?? null;
        $data['login'] = $this->normalizeAccessValue($request->input('login'));
        $data['ssh_host'] = $this->normalizeAccessValue($request->input('ssh_host'));
        $data['ssh_login'] = $this->normalizeAccessValue($request->input('ssh_login'));

        $plainPassword = $this->normalizeAccessValue($request->input('password'));
        if ($plainPassword !== '') {
            $data['password'] = Crypt::encryptString($plainPassword);
        }

        $plainSshPassword = $this->normalizeAccessValue($request->input('ssh_password'));
        if ($plainSshPassword !== '') {
            $data['ssh_password'] = Crypt::encryptString($plainSshPassword);
        }

        $account = HostingAccount::create($data);
        $account->sites()->sync($siteIds);
        $this->log($account, 'hosting_account.created', $account->toArray());

        return back()->with('success', __('portal.saved'));
    }

    public function edit(HostingAccount $hostingAccount)
    {
        return view('portal.hosting-accounts.edit', [
            'account' => $hostingAccount->load(['hosting', 'sites', 'company']),
            'hostings' => Hosting::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'passwordValue' => $hostingAccount->decryptedPassword(),
            'sshPasswordValue' => $hostingAccount->decryptedSshPassword(),
        ]);
    }

    public function update(Request $request, HostingAccount $hostingAccount)
    {
        $data = $request->validate([
            'hosting_id' => ['required', 'exists:hostings,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'ssh_host' => ['nullable', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_login' => ['nullable', 'string', 'max:255'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $siteIds = array_values(array_filter($data['site_ids']));
        $data['company_id'] = $data['company_id'] ?? null;
        $data['login'] = $this->normalizeAccessValue($request->input('login'));
        $data['ssh_host'] = $this->normalizeAccessValue($request->input('ssh_host'));
        $data['ssh_login'] = $this->normalizeAccessValue($request->input('ssh_login'));

        $plainPassword = $this->normalizeAccessValue($request->input('password'));
        if ($plainPassword !== '') {
            $data['password'] = Crypt::encryptString($plainPassword);
        } else {
            unset($data['password']);
        }

        $plainSshPassword = $this->normalizeAccessValue($request->input('ssh_password'));
        if ($plainSshPassword !== '') {
            $data['ssh_password'] = Crypt::encryptString($plainSshPassword);
        } else {
            unset($data['ssh_password']);
        }

        $before = $hostingAccount->toArray();
        $hostingAccount->update($data);
        $hostingAccount->sites()->sync($siteIds);
        $this->log($hostingAccount, 'hosting_account.updated', ['before' => $before, 'after' => $hostingAccount->fresh()->toArray()]);

        return back()->with('success', __('portal.saved'));
    }

    public function destroy(HostingAccount $hostingAccount)
    {
        $hostingAccount->delete();
        $this->log($hostingAccount, 'hosting_account.deleted', $hostingAccount->toArray());

        return back()->with('success', __('portal.deleted'));
    }

    public function restore(int $hostingAccount)
    {
        $record = HostingAccount::withTrashed()->findOrFail($hostingAccount);
        $record->restore();
        $this->log($record, 'hosting_account.restored', []);

        return back()->with('success', __('portal.restored'));
    }

    public function forceDelete(int $hostingAccount)
    {
        $record = HostingAccount::withTrashed()->findOrFail($hostingAccount);
        $record->forceDelete();
        $this->log($record, 'hosting_account.force_deleted', []);

        return back()->with('success', __('portal.deleted_permanently'));
    }

    protected function log(HostingAccount $account, string $action, array $properties): void
    {
        $activity = ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'properties' => $properties,
        ]);

        $activity->subject()->associate($account);
        $activity->save();
    }

    protected function normalizeAccessValue(mixed $value): string
    {
        return trim((string) $value);
    }
}
