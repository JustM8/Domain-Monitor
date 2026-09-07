<?php

namespace App\Modules\TelegramSupport\Models;

use App\Models\User;
use App\Modules\Shared\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'support_client_id',
        'support_topic_id',
        'company_id',
        'number',
        'title',
        'status',
        'assigned_role',
        'telegram_chat_id',
        'telegram_thread_id',
        'last_message_at',
        'closed_at',
        'closed_by_user_id',
        'closed_note',
    ];

    protected $casts = [
        'telegram_chat_id' => 'string',
        'telegram_thread_id' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(SupportClient::class, 'support_client_id');
    }

    public function topic()
    {
        return $this->belongsTo(SupportTopic::class, 'support_topic_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function messages()
    {
        return $this->hasManyThrough(
            SupportMessage::class,
            SupportTicket::class,
            'support_session_id',
            'support_ticket_id'
        )->orderBy('support_messages.id');
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'support_session_id')->orderBy('number_in_session');
    }

    public function activeTicket()
    {
        return $this->hasOne(SupportTicket::class, 'support_session_id')
            ->whereIn('status', [
                SupportTicket::STATUS_NEW,
                SupportTicket::STATUS_IN_PROGRESS,
            ])
            ->latestOfMany('updated_at');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => __('portal.support.status.open'),
            self::STATUS_IN_PROGRESS => __('portal.support.status.in_progress'),
            self::STATUS_CLOSED => __('portal.support.status.closed'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'warning',
            self::STATUS_IN_PROGRESS => 'primary',
            self::STATUS_CLOSED => 'success',
            default => 'secondary',
        };
    }

    public function displayLabel(): string
    {
        return trim(implode(' · ', array_filter([
            $this->number,
            $this->client?->displayName(),
            $this->topic?->displayLabel(),
        ])));
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
