<?php

namespace App\Modules\TelegramSupport\Models;

use App\Models\User;
use App\Modules\Shared\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_SETTINGS = 'settings';
    public const TYPE_ERROR = 'error';
    public const TYPE_FEATURE = 'feature';

    protected $fillable = [
        'support_client_id',
        'support_session_id',
        'company_id',
        'number',
        'number_in_session',
        'type',
        'subject',
        'description',
        'status',
        'assigned_role',
        'topic_thread_id',
        'first_response_at',
        'last_client_message_at',
        'last_staff_message_at',
        'closed_at',
        'closed_by_user_id',
        'closed_note',
        'customer_rating',
        'customer_rating_comment',
        'customer_rated_at',
        'sent_to_pm',
        'sent_to_pm_at',
        'sent_to_pm_by_user_id',
    ];

    protected $casts = [
        'topic_thread_id' => 'integer',
        'number_in_session' => 'integer',
        'first_response_at' => 'datetime',
        'last_client_message_at' => 'datetime',
        'last_staff_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'customer_rated_at' => 'datetime',
        'sent_to_pm' => 'boolean',
        'sent_to_pm_at' => 'datetime',
        'customer_rating' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(SupportClient::class, 'support_client_id');
    }

    public function session()
    {
        return $this->belongsTo(SupportSession::class, 'support_session_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sentToPmBy()
    {
        return $this->belongsTo(User::class, 'sent_to_pm_by_user_id');
    }

    public static function typeOptions(): array
    {
        $topics = SupportTopic::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $options = $topics->mapWithKeys(fn (SupportTopic $topic) => [
            $topic->code => $topic->displayLabel(),
        ])->all();

        return $options ?: [
            self::TYPE_CONSULTATION => __('portal.support.type.consultation'),
            self::TYPE_SETTINGS => __('portal.support.type.settings'),
            self::TYPE_ERROR => __('portal.support.type.error'),
            self::TYPE_FEATURE => __('portal.support.type.feature'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => __('portal.support.status.new'),
            self::STATUS_IN_PROGRESS => __('portal.support.status.in_progress'),
            self::STATUS_CLOSED => __('portal.support.status.closed'),
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? Str::headline((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'warning',
            self::STATUS_IN_PROGRESS => 'primary',
            self::STATUS_CLOSED => 'success',
            default => 'secondary',
        };
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_CONSULTATION => 'info',
            self::TYPE_SETTINGS => 'secondary',
            self::TYPE_ERROR => 'danger',
            self::TYPE_FEATURE => 'warning',
            default => 'light',
        };
    }

    public function responsibleRoleLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CONSULTATION => 'Support',
            self::TYPE_SETTINGS => 'Integration Manager',
            self::TYPE_ERROR => 'Support',
            self::TYPE_FEATURE => 'PM',
            default => __('portal.empty'),
        };
    }

    public function responsibleLabel(): string
    {
        $topic = $this->session?->topic;

        if ($topic) {
            $responsible = $topic->responsibleLabel();

            if (filled($responsible) && $responsible !== __('portal.empty')) {
                return $responsible;
            }
        }

        return $this->responsibleRoleLabel();
    }

    public static function responsibleRoleForType(string $type): string
    {
        return match ($type) {
            self::TYPE_CONSULTATION => 'Support',
            self::TYPE_SETTINGS => 'Integration Manager',
            self::TYPE_ERROR => 'Support',
            self::TYPE_FEATURE => 'PM',
            default => __('portal.empty'),
        };
    }

    public function displayLabel(): string
    {
        $number = str_pad((string) ($this->number_in_session ?? '?'), 5, '0', STR_PAD_LEFT);

        return __('portal.support.issue_number', [
            'number' => $number,
        ]);
    }

    public function sessionLabel(): string
    {
        return trim(implode(' · ', array_filter([
            $this->session?->number,
            $this->session?->topic?->displayLabel(),
            $this->client?->displayName(),
        ])));
    }

    public function firstResponseSeconds(): ?int
    {
        if (! $this->first_response_at) {
            return null;
        }

        return max(0, $this->created_at?->diffInSeconds($this->first_response_at) ?? 0);
    }

    public function closureSeconds(): ?int
    {
        if (! $this->closed_at) {
            return null;
        }

        return max(0, $this->created_at?->diffInSeconds($this->closed_at) ?? 0);
    }

    public function clientFollowUpCount(): int
    {
        $messages = $this->messages()->get(['direction']);
        $firstStaffIndex = $messages->search(fn (SupportMessage $message) => $message->direction === 'staff');

        if ($firstStaffIndex === false) {
            return max(0, $messages->where('direction', 'client')->count() - 1);
        }

        return $messages->slice($firstStaffIndex + 1)->where('direction', 'client')->count();
    }

    public function responseQualityScore(): int
    {
        $followUps = $this->clientFollowUpCount();

        return max(0, 100 - ($followUps * 20));
    }
}
