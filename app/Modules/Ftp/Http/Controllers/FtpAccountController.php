<?php

namespace App\Modules\Ftp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FtpAccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $accounts = FtpAccount::query()
            ->with(['site', 'sites', 'company'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('host', 'like', "%{$search}%")
                        ->orWhere('login', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
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

        return view('portal.ftp.index', [
            'accounts' => $accounts,
            'sites' => Site::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'trashedAccounts' => FtpAccount::onlyTrashed()->with('site')->latest('deleted_at')->limit(20)->get(),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.ftp.add', [
            'sites' => Site::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'requires_ip_access' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $siteIds = array_values(array_filter($data['site_ids'] ?? []));
        $data['site_id'] = $siteIds[0] ?? null;
        $data['company_id'] = $data['company_id'] ?? null;
        $data['host'] = $this->normalizeAccessValue($request->input('host'));
        $data['login'] = $this->normalizeAccessValue($request->input('login'));
        $plainPassword = $this->normalizeAccessValue($request->input('password'));

        if ($plainPassword !== '') {
            $data['password'] = Crypt::encryptString($plainPassword);
        }

        $data['requires_ip_access'] = $request->boolean('requires_ip_access');
        $data['port'] = $data['port'] ?? 21;
        $account = FtpAccount::create($data);
        $account->sites()->sync($siteIds);
        $this->log($account, 'ftp.created', $account->toArray());

        return back()->with('success', __('portal.saved'));
    }

    public function edit(FtpAccount $ftpAccount)
    {
        return view('portal.ftp.edit', [
            'account' => $ftpAccount->load(['site', 'sites', 'company']),
            'sites' => Site::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'passwordValue' => $ftpAccount->decryptedPassword(),
        ]);
    }

    public function filezilla(FtpAccount $ftpAccount)
    {
        $account = $ftpAccount->loadMissing(['company', 'sites']);
        $password = $account->decryptedPassword() ?? '';
        $selectedSite = $account->sites->first() ?? $account->site;
        $siteName = $selectedSite?->name ?? $account->host;
        $groupName = $account->company?->name ?? 'FTP';
        $exportName = Str::slug($siteName ?: $account->host) ?: 'ftp-account';
        $escape = fn (mixed $value): string => htmlspecialchars(trim((string) $value), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<FileZilla3>
  <Servers>
    <Server>
      <Host>{$escape($account->host)}</Host>
      <Port>{$escape($account->port)}</Port>
      <Protocol>0</Protocol>
      <Type>0</Type>
      <User>{$escape($account->login)}</User>
      <Pass>{$escape($password)}</Pass>
      <Logontype>1</Logontype>
      <TimezoneOffset>0</TimezoneOffset>
      <PasvMode>MODE_DEFAULT</PasvMode>
      <MaximumMultipleConnections>0</MaximumMultipleConnections>
      <EncodingType>Auto</EncodingType>
      <BypassProxy>0</BypassProxy>
      <Name>{$escape($siteName)}</Name>
      <Comments>{$escape($account->path)}</Comments>
      <SyncBrowsing>0</SyncBrowsing>
      <Color>0</Color>
      <SortMode>0</SortMode>
      <Selected>0</Selected>
      <LocalDir></LocalDir>
      <RemoteDir>{$escape($account->path)}</RemoteDir>
      <ModifiedTime>0</ModifiedTime>
    </Server>
  </Servers>
  <Bookmarks>
    <Bookmark>
      <Name>{$escape($groupName)}</Name>
      <LocalDir></LocalDir>
      <RemoteDir>{$escape($account->path)}</RemoteDir>
    </Bookmark>
  </Bookmarks>
</FileZilla3>
XML;

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $exportName . '-filezilla.xml"',
        ]);
    }

    public function update(Request $request, FtpAccount $ftpAccount)
    {
        $data = $request->validate([
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'requires_ip_access' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $siteIds = array_values(array_filter($data['site_ids'] ?? []));
        $data['site_id'] = $siteIds[0] ?? null;
        $data['company_id'] = $data['company_id'] ?? null;
        $data['host'] = $this->normalizeAccessValue($request->input('host'));
        $data['login'] = $this->normalizeAccessValue($request->input('login'));
        $plainPassword = $this->normalizeAccessValue($request->input('password'));

        if ($plainPassword !== '') {
            $data['password'] = Crypt::encryptString($plainPassword);
        } else {
            unset($data['password']);
        }

        $data['requires_ip_access'] = $request->boolean('requires_ip_access');
        $data['port'] = $data['port'] ?? 21;
        $before = $ftpAccount->toArray();
        $ftpAccount->update($data);
        $ftpAccount->sites()->sync($siteIds);
        $this->log($ftpAccount, 'ftp.updated', ['before' => $before, 'after' => $ftpAccount->fresh()->toArray()]);

        return back()->with('success', __('portal.saved'));
    }

    public function destroy(FtpAccount $ftpAccount)
    {
        $ftpAccount->delete();
        $this->log($ftpAccount, 'ftp.deleted', $ftpAccount->toArray());

        return back()->with('success', __('portal.deleted'));
    }

    public function restore(int $ftpAccount)
    {
        $record = FtpAccount::withTrashed()->findOrFail($ftpAccount);
        $record->restore();
        $this->log($record, 'ftp.restored', []);

        return back()->with('success', __('portal.restored'));
    }

    public function forceDelete(int $ftpAccount)
    {
        $record = FtpAccount::withTrashed()->findOrFail($ftpAccount);
        $record->forceDelete();
        $this->log($record, 'ftp.force_deleted', []);

        return back()->with('success', __('portal.deleted_permanently'));
    }

    protected function log(FtpAccount $account, string $action, array $properties): void
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
