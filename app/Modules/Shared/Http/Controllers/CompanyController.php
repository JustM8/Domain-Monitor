<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $companies = Company::query()
            ->withCount(['sites', 'ftpAccounts', 'hostingAccounts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('manager_name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.companies.index', [
            'companies' => $companies,
            'trashedCompanies' => Company::onlyTrashed()->latest('deleted_at')->limit(20)->get(),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.companies.add');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $company = Company::create($data);
        $this->log($company, 'company.created', $company->toArray());

        return back()->with('success', __('portal.saved'));
    }

    public function edit(Company $company)
    {
        return view('portal.companies.edit', [
            'company' => $company->load([
                'sites.status',
                'sites.ftpAccounts',
                'ftpAccounts.site',
                'hostingAccounts.hosting',
                'hostingAccounts.sites',
            ]),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $before = $company->toArray();
        $company->update($data);
        $this->log($company, 'company.updated', ['before' => $before, 'after' => $company->fresh()->toArray()]);

        return back()->with('success', __('portal.saved'));
    }

    public function destroy(Company $company)
    {
        $company->delete();
        $this->log($company, 'company.deleted', $company->toArray());

        return back()->with('success', __('portal.deleted'));
    }

    public function restore(int $company)
    {
        $record = Company::withTrashed()->findOrFail($company);
        $record->restore();
        $this->log($record, 'company.restored', []);

        return back()->with('success', __('portal.restored'));
    }

    public function forceDelete(int $company)
    {
        $record = Company::withTrashed()->findOrFail($company);
        $record->forceDelete();
        $this->log($record, 'company.force_deleted', []);

        return back()->with('success', __('portal.deleted_permanently'));
    }

    protected function log(Company $company, string $action, array $properties): void
    {
        $activity = ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'properties' => $properties,
        ]);

        $activity->subject()->associate($company);
        $activity->save();
    }
}
