<?php

namespace App\Modules\TelegramSupport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'sort_order',
        'assigned_role',
        'telegram_chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(SupportSession::class);
    }

    public function responsibleUsers()
    {
        return $this->belongsToMany(User::class, 'support_topic_user')
            ->withTimestamps()
            ->orderBy('users.full_name')
            ->orderBy('users.name');
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->label);

        if ($label !== '' && ! str_starts_with($label, 'portal.')) {
            return $label;
        }

        return __('portal.support.type.' . $this->code);
    }

    public function responsibleLabel(): string
    {
        $responsibleUsers = $this->relationLoaded('responsibleUsers')
            ? $this->responsibleUsers
            : $this->responsibleUsers()->get();

        if ($responsibleUsers->isNotEmpty()) {
            return $responsibleUsers->map(fn (User $user) => $user->displayName())->implode(', ');
        }

        return trim((string) $this->assigned_role) !== ''
            ? (string) $this->assigned_role
            : __('portal.empty');
    }

    public function hasSupportChat(): bool
    {
        return filled($this->telegram_chat_id) && $this->is_active;
    }

    public static function seedCatalog(): array
    {
        return [
            ['code' => 'consultation', 'label' => __('portal.support.type.consultation'), 'sort_order' => 1, 'assigned_role' => 'Support', 'is_active' => true],
            ['code' => 'settings', 'label' => __('portal.support.type.settings'), 'sort_order' => 2, 'assigned_role' => 'Integration Manager', 'is_active' => true],
            ['code' => 'error', 'label' => __('portal.support.type.error'), 'sort_order' => 3, 'assigned_role' => 'Support', 'is_active' => true],
            ['code' => 'feature', 'label' => __('portal.support.type.feature'), 'sort_order' => 4, 'assigned_role' => 'PM', 'is_active' => true],
        ];
    }
}
