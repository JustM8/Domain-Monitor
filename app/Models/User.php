<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'position',
        'full_name',
        'email',
        'password',
        'role_id',
        'is_active',
        'approval_status',
        'last_login_at',
        'telegram_chat_id',
        'telegram_username',
        'telegram_link_token',
        'telegram_link_requested_at',
        'telegram_link_expires_at',
        'telegram_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'telegram_link_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'telegram_link_requested_at' => 'datetime',
        'telegram_link_expires_at' => 'datetime',
        'telegram_verified_at' => 'datetime',
        'telegram_access_revoked_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(\App\Modules\Shared\Models\Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isPm(): bool
    {
        return $this->role?->name === 'pm';
    }

    public function isDeveloper(): bool
    {
        return $this->role?->name === 'developer';
    }

    public function displayName(): string
    {
        return $this->full_name ?: $this->name;
    }

    public function portalIsActive(): bool
    {
        return $this->is_active && $this->approval_status === 'active';
    }

    public function canPortal(string $ability): bool
    {
        return \App\Modules\Shared\Support\PortalAccess::allows($this, $ability);
    }

    public function canUseAccessBot(): bool
    {
        return $this->canPortal('access.use') && $this->telegram_access_revoked_at === null;
    }

    public function portalHome(): string
    {
        if (! $this->portalIsActive()) {
            return '/portal/pending-approval';
        }
        if ($this->role?->name === 'manager') {
            return '/portal/support';
        }

        return $this->canPortal('dashboard.read') ? '/portal' : '/portal/sites';
    }

    public function telegramIsLinked(): bool
    {
        return filled($this->telegram_chat_id) && filled($this->telegram_verified_at);
    }

    public function telegramIsPending(): bool
    {
        return filled($this->telegram_link_token) && blank($this->telegram_verified_at);
    }

    public function telegramIsApproved(): bool
    {
        return filled($this->telegram_verified_at);
    }

    public function telegramNeedsBotStart(): bool
    {
        return $this->telegramIsApproved() && blank($this->telegram_chat_id);
    }
}
