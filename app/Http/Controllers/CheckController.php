<?php
namespace App\Http\Controllers;

use App\Models\Check;

class CheckController extends Controller
{
    public function index()
    {
        $query = Check::with('domain')
            ->whereHas('domain', fn($q) => $q->where('user_id', auth()->id()));

        if (request('domain_id')) {
            $query->where('domain_id', request('domain_id'));
        }

        $checks = $query->latest('checked_at')->paginate(15);

        $domains = auth()->user()->domains()->get();

        return view('checks.index', compact('checks', 'domains'));
    }
}
