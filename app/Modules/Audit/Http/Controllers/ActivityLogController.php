<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $logs = ActivityLog::with(['user', 'subject'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('properties', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('subject_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('portal.activity.index', [
            'logs' => $logs,
            'search' => $search,
        ]);
    }
}
