<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Shared\Models\Company;
use App\Modules\Site\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrashController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $siteItems = Site::onlyTrashed()->with(['status', 'company'])->latest('deleted_at')->get();
        $companyItems = Company::onlyTrashed()->latest('deleted_at')->get();
        $ftpItems = FtpAccount::onlyTrashed()->with(['company', 'sites'])->latest('deleted_at')->get();
        $hostingItems = Hosting::onlyTrashed()->latest('deleted_at')->get();
        $hostingAccountItems = HostingAccount::onlyTrashed()->with(['company', 'hosting', 'sites'])->latest('deleted_at')->get();

        if ($search !== '') {
            $needle = Str::lower($search);

            $siteItems = $siteItems->filter(function (Site $item) use ($needle) {
                return Str::contains(Str::lower(implode(' ', array_filter([
                    $item->name,
                    $item->url,
                    $item->admin_url,
                    $item->company?->name,
                    $item->status?->name,
                ]))), $needle);
            })->values();

            $companyItems = $companyItems->filter(function (Company $item) use ($needle) {
                return Str::contains(Str::lower(implode(' ', array_filter([
                    $item->name,
                    $item->manager_name,
                    $item->contact,
                ]))), $needle);
            })->values();

            $ftpItems = $ftpItems->filter(function (FtpAccount $item) use ($needle) {
                return Str::contains(Str::lower(implode(' ', array_filter([
                    $item->host,
                    $item->login,
                    $item->path,
                    $item->company?->name,
                    $item->sites?->pluck('name')->implode(' '),
                ]))), $needle);
            })->values();

            $hostingItems = $hostingItems->filter(function (Hosting $item) use ($needle) {
                return Str::contains(Str::lower(implode(' ', array_filter([
                    $item->name,
                    $item->provider,
                    $item->panel_url,
                ]))), $needle);
            })->values();

            $hostingAccountItems = $hostingAccountItems->filter(function (HostingAccount $item) use ($needle) {
                return Str::contains(Str::lower(implode(' ', array_filter([
                    $item->title,
                    $item->login,
                    $item->ssh_host,
                    $item->company?->name,
                    $item->hosting?->name,
                    $item->sites?->pluck('name')->implode(' '),
                ]))), $needle);
            })->values();
        }

        return view('portal.trash.index', [
            'sites' => $siteItems,
            'companies' => $companyItems,
            'ftpAccounts' => $ftpItems,
            'hostings' => $hostingItems,
            'hostingAccounts' => $hostingAccountItems,
            'search' => $search,
        ]);
    }
}
