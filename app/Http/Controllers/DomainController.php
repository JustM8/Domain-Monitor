<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainCheckService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $domains = Domain::with('lastCheck')
            ->where('user_id', auth()->id())
            ->get();

        return view('domains.index', compact('domains'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Domain::create([
            'user_id' => auth()->id(),
            'domain' => $request->domain,
            'check_interval' => $request->check_interval ?? 60,
            'timeout' => $request->timeout ?? 5,
            'method' => $request->get('method', 'GET'),
        ]);

        return back()->with('success', 'Added');
    }

    /**
     * Display the specified resource.
     */
    public function show(Domain $domain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Domain $domain)
    {
        return view('domains.edit', compact('domain'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Domain $domain)
    {
        $domain->update([
            'domain' => $request->input('domain'),
            'check_interval' => $request->input('check_interval', 60),
            'timeout' => $request->input('timeout', 5),
            'method' => $request->input('method', 'GET'),
        ]);

        return back()->with('success', 'Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Domain $domain)
    {
        abort_if($domain->user_id !== auth()->id(), 403);

        $domain->delete();

        return redirect()->back()->with('success', 'Domain deleted');
    }

    public function check(Domain $domain, DomainCheckService $service)
    {
        $service->check($domain);

        return back();
    }
}
