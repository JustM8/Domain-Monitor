<?php

namespace App\Modules\TelegramSupport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'direction',
        'body',
        'payload',
        'telegram_message_id',
        'telegram_chat_id',
        'telegram_username',
        'sent_by_user_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
