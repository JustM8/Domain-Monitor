<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Shared\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('role', function ($query) use ($search) {
                            $query->where('label', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('sort_order')->get(),
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.users.add', [
            'roles' => Role::orderBy('sort_order')->get(),
        ]);
    }

    public function edit(User $user)
    {
        return view('portal.users.edit', [
            'user' => $user->load('role'),
            'roles' => Role::orderBy('sort_order')->get(),
            'currentUserId' => auth()->id(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'new_role_name' => trim((string) $request->input('new_role_name')),
            'new_role_label' => trim((string) $request->input('new_role_label')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['nullable', 'exists:roles,id', 'required_without:new_role_name'],
            'new_role_name' => ['nullable', 'string', 'max:255', 'required_without:role_id'],
            'new_role_label' => ['nullable', 'string', 'max:255'],
            'new_role_sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['approval_status'] = $data['is_active'] ? 'active' : 'pending';
        $data['role_id'] = $this->resolveRoleId($request, $data['role_id'] ?? null);
        unset($data['new_role_name'], $data['new_role_label'], $data['new_role_sort_order']);

        User::create($data);

        return back()->with('success', __('portal.saved'));
    }

    public function update(Request $request, User $user)
    {
        $request->merge([
            'new_role_name' => trim((string) $request->input('new_role_name')),
            'new_role_label' => trim((string) $request->input('new_role_label')),
        ]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($user->id !== auth()->id()) {
            $rules['role_id'] = ['nullable', 'exists:roles,id', 'required_without:new_role_name'];
            $rules['new_role_name'] = ['nullable', 'string', 'max:255', 'required_without:role_id'];
            $rules['new_role_label'] = ['nullable', 'string', 'max:255'];
            $rules['new_role_sort_order'] = ['nullable', 'integer', 'min:0', 'max:9999'];
        }

        $data = $request->validate($rules);

        if (blank($request->input('password'))) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($user->id !== auth()->id()) {
            $data['role_id'] = $this->resolveRoleId($request, $data['role_id'] ?? null);
            unset($data['new_role_name'], $data['new_role_label'], $data['new_role_sort_order']);
        } else {
            unset($data['role_id'], $data['new_role_name'], $data['new_role_label'], $data['new_role_sort_order']);
        }

        if ($user->id === auth()->id()) {
            unset($data['is_active']);
        } else {
            $data['is_active'] = $request->boolean('is_active', false);
            $data['approval_status'] = $data['is_active'] ? 'active' : 'blocked';
        }

        $user->update($data);

        return back()->with('success', __('portal.saved'));
    }

    public function sendVerification(User $user)
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('success', __('portal.email_verified'));
        }

        $user->sendEmailVerificationNotification();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user.verification_sent',
            'properties' => ['user_id' => $user->id, 'email' => $user->email],
        ])->subject()->associate($user)->save();

        return back()->with('success', __('portal.verification_sent'));
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('portal.self_role_lock'));
        }

        $before = $user->toArray();
        $user->update([
            'is_active' => ! $user->is_active,
            'approval_status' => $user->is_active ? 'blocked' : 'active',
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user.toggle_active',
            'properties' => [
                'before' => $before,
                'after' => $user->fresh()->toArray(),
            ],
        ])->subject()->associate($user)->save();

        return back()->with('success', __('portal.saved'));
    }

    public function approve(User $user)
    {
        DB::transaction(function () use ($user) {
            $target = User::lockForUpdate()->findOrFail($user->id);
            $actor = User::with('role')->lockForUpdate()->findOrFail(auth()->id());
            abort_unless(\App\Modules\Shared\Support\PortalAccess::canApprove($actor, $target), 403);
            $target->update(['is_active' => true, 'approval_status' => 'active']);
            $log = new ActivityLog(['user_id' => $actor->id, 'action' => 'user.approved', 'properties' => ['user_id' => $target->id]]);
            $log->subject()->associate($target);
            $log->save();
        });

        return back()->with('success', __('portal.saved'));
    }

    protected function resolveRoleId(Request $request, mixed $roleId): ?int
    {
        if (filled($roleId)) {
            return (int) $roleId;
        }

        $roleName = trim((string) $request->input('new_role_name'));
        if ($roleName === '') {
            return null;
        }

        $role = Role::query()->updateOrCreate(
            ['name' => Str::slug($roleName)],
            [
                'label' => trim((string) $request->input('new_role_label')) ?: Str::headline($roleName),
                'sort_order' => max(0, (int) $request->input('new_role_sort_order', 0)),
            ]
        );

        return $role->id;
    }

    protected function telegramFailureReason(array $response): string
    {
        $reason = trim((string) data_get($response, 'description', ''));

        if ($reason === '') {
            $reason = trim((string) data_get($response, 'error', 'Telegram request failed.'));
        }

        return Str::limit($reason, 120);
    }
}
