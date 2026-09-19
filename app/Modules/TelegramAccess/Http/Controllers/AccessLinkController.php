<?php

namespace App\Modules\TelegramAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TelegramAccess\Services\AccessLinkService;
use Illuminate\Http\Request;

final class AccessLinkController extends Controller
{
    public function request(Request $request, AccessLinkService $links)
    {
        $username = ltrim((string) config('telegram_access.bot_username'), '@');
        abort_unless(preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username), 503, 'Access-бот ще не налаштований.');
        $token = $links->issue($request->user()->fresh('role'));

        return redirect()->away('https://t.me/'.$username.'?start='.$token);
    }

    public function approve(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin() && $request->user()->id !== $user->id && $user->canPortal('access.use'), 403);
        $user->forceFill(['telegram_access_revoked_at' => null])->save();
        $this->audit($user, 'telegram.approved');

        return back()->with('status', 'Доступ до Access-бота дозволено. Користувач має підключити Telegram у кабінеті.');
    }

    public function revoke(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin() && $request->user()->id !== $user->id, 403);
        $user->forceFill([
            'telegram_access_revoked_at' => now(), 'telegram_chat_id' => null,
            'telegram_verified_at' => null, 'telegram_link_token' => null, 'telegram_link_expires_at' => null,
        ])->save();
        $this->audit($user, 'telegram.revoked');

        return back()->with('status', 'Доступ до Access-бота відкликано.');
    }

    private function audit(User $user, string $action): void
    {
        $log = new \App\Modules\Shared\Models\ActivityLog(['user_id' => auth()->id(), 'action' => $action, 'properties' => ['user_id' => $user->id]]);
        $log->subject()->associate($user);
        $log->save();
    }
}
