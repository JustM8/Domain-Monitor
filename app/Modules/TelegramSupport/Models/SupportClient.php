<?php

namespace App\Modules\TelegramSupport\Models;

use App\Modules\Shared\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'company_name',
        'telegram_user_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'full_name',
        'email',
        'phone',
        'position',
        'description',
        'state',
        'draft_data',
        'last_active_at',
        'current_support_session_id',
        'current_support_ticket_id',
        'pending_support_ticket_id',
    ];

    protected $casts = [
        'draft_data' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'support_client_id');
    }

    public function sessions()
    {
        return $this->hasMany(SupportSession::class, 'support_client_id');
    }

    public function currentSession()
    {
        return $this->belongsTo(SupportSession::class, 'current_support_session_id');
    }

    public function currentTicket()
    {
        return $this->belongsTo(SupportTicket::class, 'current_support_ticket_id');
    }

    public function pendingSupportTicket()
    {
        return $this->belongsTo(SupportTicket::class, 'pending_support_ticket_id');
    }

    public function activeSession()
    {
        return $this->hasOne(SupportSession::class, 'support_client_id')
            ->whereIn('status', [
                SupportSession::STATUS_OPEN,
                SupportSession::STATUS_IN_PROGRESS,
            ])
            ->latestOfMany('last_message_at');
    }

    public function displayName(): string
    {
        return $this->full_name
            ?: $this->telegram_username
            ?: trim((string) $this->telegram_first_name . ' ' . (string) $this->telegram_last_name)
            ?: __('portal.empty');
    }

    public function companyDisplayName(): string
    {
        return $this->company?->name ?: $this->company_name ?: __('portal.empty');
    }

    public function stateLabel(): string
    {
        return self::stateOptions()[$this->state] ?? Str::headline((string) $this->state);
    }

    public static function stateOptions(): array
    {
        return [
            'await_company' => 'Очікує компанію',
            'await_name' => 'Очікує ПІБ',
            'await_email' => 'Очікує email',
            'await_phone' => 'Очікує телефон',
            'await_position' => 'Очікує посаду',
            'await_rating_comment' => 'Очікує коментар до оцінки',
            'await_topic' => 'Очікує тему',
            'await_message' => 'Очікує повідомлення',
            'ready' => 'Готовий',
        ];
    }

    public function isReady(): bool
    {
        return filled($this->company_name)
            && filled($this->full_name)
            && filled($this->email)
            && filled($this->phone);
    }

    public function requiresCompanyValidation(): bool
    {
        return filled($this->company_name) && blank($this->company_id);
    }
}
