<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('portal.profile.edit', [
            'telegramBotUsername' => config('services.telegram.bot_username'),
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

    public function requestTelegramAccess(Request $request)
    {
        $user = $request->user();

        if (! config('services.telegram.bot_username')) {
            Log::warning('telegram_access.portal.request_failed', [
                'user_id' => $user->id,
                'reason' => 'bot_username_missing',
            ]);

            return back()->with('error', __('portal.telegram_not_configured'));
        }

        if ($user->telegramIsLinked()) {
            Log::info('telegram_access.portal.request_skipped_already_linked', [
                'user_id' => $user->id,
                'telegram_chat_id' => $user->telegram_chat_id,
            ]);

            return back()->with('success', __('portal.telegram_already_linked'));
        }

        if (filled($user->telegram_link_token)) {
            $botUsername = config('services.telegram.bot_username');
            $link = "https://t.me/{$botUsername}?start={$user->telegram_link_token}";

            Log::debug('telegram_access.portal.request_reused_token', [
                'user_id' => $user->id,
                'token_prefix' => mb_substr((string) $user->telegram_link_token, 0, 8),
                'link_requested_at' => $user->telegram_link_requested_at?->toDateTimeString(),
            ]);

            return redirect()->away($link);
        }

        $token = (string) Str::random(48);
        $requestedAt = now();
        $expiresAt = now()->addDay();

        $user->forceFill([
            'telegram_link_token' => $token,
            'telegram_link_requested_at' => $requestedAt,
            'telegram_link_expires_at' => $expiresAt,
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'telegram.requested',
            'properties' => [
                'user_id' => $user->id,
                'telegram_username' => $user->telegram_username,
                'token_prefix' => mb_substr($token, 0, 8),
                'requested_at' => $requestedAt->toDateTimeString(),
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
        ])->subject()->associate($user)->save();

        $botUsername = config('services.telegram.bot_username');
        $link = "https://t.me/{$botUsername}?start={$token}";

        Log::debug('telegram_access.portal.request_redirect', [
            'user_id' => $user->id,
            'link' => $link,
        ]);

        return redirect()->away($link);
    }
}
