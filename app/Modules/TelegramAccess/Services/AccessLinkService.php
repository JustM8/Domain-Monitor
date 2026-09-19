<?php

namespace App\Modules\TelegramAccess\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccessLinkService
{
    public function issue(User $user): string
    {
        abort_unless($user->canUseAccessBot(), 403);
        $token = Str::random(48);
        $user->forceFill([
            'telegram_link_token' => hash('sha256', $token),
            'telegram_link_requested_at' => now(),
            'telegram_link_expires_at' => now()->addMinutes(config('telegram_access.link_minutes', 15)),
        ])->save();

        return $token;
    }

    public function consume(string $token, string $telegramId, ?string $username): ?User
    {
        if (strlen($token) !== 48 || ! ctype_digit($telegramId) || (int) $telegramId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($token, $telegramId, $username) {
            $user = User::query()->with('role')->where('telegram_link_token', hash('sha256', $token))->lockForUpdate()->first();
            if (! $user || ! $user->canUseAccessBot() || ! $user->telegram_link_expires_at || $user->telegram_link_expires_at->isPast()) {
                return null;
            }
            if (User::query()->where('telegram_chat_id', $telegramId)->where('id', '!=', $user->id)->exists()) {
                return null;
            }
            $user->forceFill([
                'telegram_chat_id' => $telegramId, 'telegram_username' => $username,
                'telegram_verified_at' => now(), 'telegram_link_token' => null, 'telegram_link_expires_at' => null,
            ])->save();

            return $user;
        });
    }
}
