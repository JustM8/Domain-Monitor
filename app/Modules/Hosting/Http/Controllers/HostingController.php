<?php

namespace App\Modules\Hosting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Hosting\Models\Hosting;
use Illuminate\Http\Request;

class HostingController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $hostings = Hosting::query()
            ->withCount('accounts')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('panel_url', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.hosting.index', [
            'hostings' => $hostings,
            'trashedHostings' => Hosting::onlyTrashed()->latest('deleted_at')->limit(20)->get(),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.hosting.add');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'panel_url' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $hosting = Hosting::create($data);
        $this->log($hosting, 'hosting.created', $hosting->toArray());

        return back()->with('success', __('portal.saved'));
    }

    public function edit(Hosting $hosting)
    {
        return view('portal.hosting.edit', compact('hosting'));
    }

    public function update(Request $request, Hosting $hosting)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'panel_url' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $before = $hosting->toArray();
        $hosting->update($data);
        $this->log($hosting, 'hosting.updated', ['before' => $before, 'after' => $hosting->fresh()->toArray()]);

        return back()->with('success', __('portal.saved'));
    }

    public function destroy(Hosting $hosting)
    {
        $hosting->delete();
        $this->log($hosting, 'hosting.deleted', $hosting->toArray());

        return back()->with('success', __('portal.deleted'));
    }

    public function restore(int $hosting)
    {
        $record = Hosting::withTrashed()->findOrFail($hosting);
        $record->restore();
        $this->log($record, 'hosting.restored', []);

        return back()->with('success', __('portal.restored'));
    }

    public function forceDelete(int $hosting)
    {
        $record = Hosting::withTrashed()->findOrFail($hosting);
        $record->forceDelete();
        $this->log($record, 'hosting.force_deleted', []);

        return back()->with('success', __('portal.deleted_permanently'));
    }

    protected function log(Hosting $hosting, string $action, array $properties): void
    {
        $activity = ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'properties' => $properties,
        ]);

        $activity->subject()->associate($hosting);
        $activity->save();
    }
}
