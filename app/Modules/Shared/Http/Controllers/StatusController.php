<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Status;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $statuses = Status::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%")
                        ->orWhere('sort_order', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->get();

        return view('portal.statuses.index', [
            'statuses' => $statuses,
            'defaultStatuses' => collect(Status::defaultCatalog()),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.statuses.add', [
            'defaultStatuses' => collect(Status::defaultCatalog()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:statuses,name'],
            'color' => ['required', 'string', 'max:32'],
            'color_value' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['color'] = $this->normalizeColor($data['color_value'] ?? '', $data['color']);
        $status = Status::create($data);
        $this->log($status, 'status.created', $status->toArray());

        return back()->with('success', __('portal.saved'));
    }

    public function edit(Status $status)
    {
        return view('portal.statuses.edit', compact('status'));
    }

    public function update(Request $request, Status $status)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:statuses,name,' . $status->id],
            'color' => ['required', 'string', 'max:32'],
            'color_value' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['color'] = $this->normalizeColor($data['color_value'] ?? '', $data['color']);
        $before = $status->toArray();
        $status->update($data);
        $this->log($status, 'status.updated', ['before' => $before, 'after' => $status->fresh()->toArray()]);

        return back()->with('success', __('portal.saved'));
    }

    public function syncDefaults()
    {
        foreach (Status::defaultCatalog() as $status) {
            Status::query()->updateOrCreate(
                ['code' => $status['code']],
                $status + ['is_archived' => false]
            );
        }

        return back()->with('success', __('portal.default_statuses_synced'));
    }

    public function archive(Status $status)
    {
        $status->update([
            'is_archived' => true,
        ]);
        $this->log($status, 'status.archived', []);

        return back()->with('success', __('portal.saved'));
    }

    public function restore(Status $status)
    {
        $status->update([
            'is_archived' => false,
        ]);
        $this->log($status, 'status.restored', []);

        return back()->with('success', __('portal.saved'));
    }

    protected function log(Status $status, string $action, array $properties): void
    {
        $activity = ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'properties' => $properties,
        ]);

        $activity->subject()->associate($status);
        $activity->save();
    }

    protected function normalizeColor(string $value, string $fallback = '#64748b'): string
    {
        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtolower($value);
        }

        if (preg_match('/^rgb\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $matches)) {
            $rgb = array_slice($matches, 1, 3);
            $rgb = array_map(fn ($channel) => max(0, min(255, (int) $channel)), $rgb);

            return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $fallback) ? strtolower($fallback) : '#64748b';
    }
}
