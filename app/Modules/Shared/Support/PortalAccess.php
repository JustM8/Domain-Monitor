<?php

namespace App\Modules\Shared\Support;

use App\Models\User;

final class PortalAccess
{
    public const ABILITIES = [
        'dashboard.read', 'sites.read', 'sites.write', 'sites.delete', 'sites.control',
        'companies.read', 'companies.write', 'companies.delete', 'statuses.read', 'statuses.write',
        'ftp.read', 'ftp.write', 'ftp.delete', 'hosting.read', 'hosting.write', 'hosting.delete',
        'hosting-accounts.read', 'hosting-accounts.write', 'hosting-accounts.delete',
        'support.read', 'support.write', 'support.reply', 'users.read', 'users.write', 'activity.read',
        'monitoring.read', 'monitoring.write', 'trash.read', 'records.purge', 'access.use',
    ];

    private const PM = [
        'dashboard.read', 'sites.read', 'sites.control', 'companies.read', 'companies.write',
        'companies.delete', 'statuses.read', 'ftp.read', 'hosting.read', 'hosting-accounts.read',
        'support.read', 'users.read', 'activity.read', 'monitoring.read', 'monitoring.write',
        'trash.read', 'access.use',
    ];

    private const DEVELOPER = [
        'sites.read', 'sites.write', 'companies.read', 'companies.write', 'statuses.read',
        'statuses.write', 'ftp.read', 'ftp.write', 'hosting.read', 'hosting.write',
        'hosting-accounts.read', 'hosting-accounts.write', 'access.use',
    ];

    public static function allows(User $user, string $ability): bool
    {
        if (! $user->portalIsActive() || ! in_array($ability, self::ABILITIES, true)) {
            return false;
        }

        return match ($user->role?->name) {
            'admin' => true,
            'pm' => in_array($ability, self::PM, true),
            'developer' => in_array($ability, self::DEVELOPER, true),
            'manager' => in_array($ability, ['support.read', 'support.reply'], true),
            default => false,
        };
    }

    public static function canApprove(User $actor, User $target): bool
    {
        return $actor->portalIsActive() && $actor->id !== $target->id
            && $target->approval_status === 'pending'
            && ($actor->isAdmin() || ($actor->isPm() && in_array($target->role?->name, ['pm', 'developer'], true)));
    }
}
