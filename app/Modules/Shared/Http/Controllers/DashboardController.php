<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\Status;
use App\Modules\Site\Models\Site;

class DashboardController extends Controller
{
    public function index()
    {
        $statuses = Status::orderBy('sort_order')->get();
        $statusLabels = $statuses->pluck('name');
        $statusCounts = $statuses->map(fn (Status $status) => Site::where('status_id', $status->id)->count());
        $statusColors = $statuses->map(fn (Status $status) => $status->color);

        $siteTypes = collect(array_keys(Site::siteTypeOptions()));
        $siteTypeCounts = $siteTypes->map(fn ($type) => Site::where('site_type', $type)->count());

        $recentSites = Site::with(['status', 'company'])
            ->latest()
            ->limit(5)
            ->get();

        $recentActivity = ActivityLog::with(['user', 'subject'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (ActivityLog $activity) {
                return [
                    'title' => $this->activityTitle($activity),
                    'subtitle' => trim(($activity->user?->displayName() ?? __('portal.system')) . ' | ' . $activity->created_at?->diffForHumans()),
                    'icon' => $this->activityIcon($activity->action),
                ];
            });

        return view('portal.dashboard', [
            'sitesCount' => Site::count(),
            'activeSitesCount' => Site::where('is_active', true)->count(),
            'disabledSitesCount' => Site::where('is_active', false)->count(),
            'companiesCount' => Company::count(),
            'hostingCount' => Hosting::count(),
            'ftpCount' => FtpAccount::count(),
            'usersCount' => User::count(),
            'recentActivity' => $recentActivity,
            'statusLabels' => $statusLabels,
            'statusCounts' => $statusCounts,
            'statusColors' => $statusColors,
            'siteTypeLabels' => $siteTypes->map(fn ($type) => Site::siteTypeOptions()[$type]),
            'siteTypeCounts' => $siteTypeCounts,
            'statusLegend' => $statuses,
            'recentSites' => $recentSites,
            'sitesWithoutCompanyCount' => Site::whereNull('company_id')->count(),
            'sitesWithoutStatusCount' => Site::whereNull('status_id')->count(),
            'environmentLabels' => collect(array_keys(Site::environmentOptions()))->map(fn ($type) => Site::environmentOptions()[$type]),
            'environmentCounts' => collect(array_keys(Site::environmentOptions()))->map(fn ($type) => Site::where('environment', $type)->count()),
        ]);
    }

    protected function activityTitle(ActivityLog $activity): string
    {
        return $activity->actionLabel();
    }

    protected function activityIcon(string $action): string
    {
        return ActivityLog::make(['action' => $action])->icon();
    }
}
