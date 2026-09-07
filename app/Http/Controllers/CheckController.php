<?php
namespace App\Http\Controllers;

use App\Models\Check;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $query = Check::with('domain')
            ->whereHas('domain', fn($q) => $q->where('user_id', auth()->id()));

        if (request('domain_id')) {
            $query->where('domain_id', request('domain_id'));
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('status_code', 'like', "%{$search}%")
                    ->orWhere('response_time', 'like', "%{$search}%")
                    ->orWhere('error', 'like', "%{$search}%")
                    ->orWhereHas('domain', function ($query) use ($search) {
                        $query->where('domain', 'like', "%{$search}%")
                            ->orWhere('method', 'like', "%{$search}%");
                    });
            });
        }

        $checks = $query->latest('checked_at')->paginate(15)->withQueryString();

        $domains = auth()->user()->domains()->get();

        return view('checks.index', compact('checks', 'domains', 'search'));
    }
}
