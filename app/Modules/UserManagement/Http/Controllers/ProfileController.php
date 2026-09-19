<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('portal.profile.edit', [
            'telegramBotUsername' => config('telegram_access.bot_username'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->full_name = $data['full_name'] ?? null;
        $user->position = $data['position'] ?? null;

        if ($request->filled('password')) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', __('portal.saved'));
    }

    public function sendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', __('portal.email_verified'));
        }

        $user->sendEmailVerificationNotification();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'profile.verification_sent',
            'properties' => ['user_id' => $user->id, 'email' => $user->email],
        ])->subject()->associate($user)->save();

        return back()->with('success', __('portal.verification_sent'));
    }
}
