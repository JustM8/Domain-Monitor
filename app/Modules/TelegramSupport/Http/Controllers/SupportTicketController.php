<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TelegramSupport\Models\SupportMessage;
use App\Modules\TelegramSupport\Models\SupportSession;
use App\Modules\TelegramSupport\Models\SupportTicket;
use App\Services\TelegramSupportBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $type = trim((string) $request->string('type'));

        $query = SupportTicket::query()
            ->with(['client', 'session.topic.responsibleUsers', 'session.tickets', 'closedBy', 'messages'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('telegram_username', 'like', "%{$search}%");
                        })
                        ->orWhereHas('session', function ($query) use ($search) {
                            $query->where('number', 'like', "%{$search}%")
                                ->orWhereHas('topic', function ($query) use ($search) {
                                    $query->where('label', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type));

        $tickets = (clone $query)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statsQuery = (clone $query);

        $stats = [
            'open' => (clone $statsQuery)->where('status', SupportTicket::STATUS_NEW)->count(),
            'in_progress' => (clone $statsQuery)->where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
            'closed' => (clone $statsQuery)->where('status', SupportTicket::STATUS_CLOSED)->count(),
            'avg_rating' => (int) round((clone $statsQuery)->whereNotNull('customer_rating')->avg('customer_rating') ?? 0),
        ];

        return view('portal.support.index', [
            'tickets' => $tickets,
            'statuses' => SupportTicket::statusOptions(),
            'types' => SupportTicket::typeOptions(),
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'stats' => $stats,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load([
            'session.topic.responsibleUsers',
            'session.tickets.messages.sentBy',
            'session.tickets.closedBy',
            'messages.sentBy',
            'closedBy',
            'sentToPmBy',
        ]);

        return view('portal.support.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicket::statusOptions(),
            'types' => SupportTicket::typeOptions(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, TelegramSupportBotService $bot)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $ticket->loadMissing('client', 'session');

        if (! $ticket->client?->telegram_chat_id) {
            return back()->with('error', __('portal.support.client_missing_chat'));
        }

        $response = $bot->sendMessage($ticket->client->telegram_chat_id, $data['body']);
        $flashType = 'success';
        $flashMessage = __('portal.support.reply_sent');

        if (! data_get($response, 'ok')) {
            $flashType = 'warning';
            $flashMessage = __('portal.support.reply_sent') . ' · ' . __('portal.telegram_notification_failed', [
                'reason' => $this->telegramFailureReason($response),
            ]);
        }

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'direction' => 'staff',
            'body' => $data['body'],
            'sent_by_user_id' => auth()->id(),
        ]);

        $ticket->forceFill([
            'status' => SupportTicket::STATUS_IN_PROGRESS,
            'first_response_at' => $ticket->first_response_at ?: now(),
            'last_staff_message_at' => now(),
        ])->save();

        if ($ticket->session) {
            $ticket->session->forceFill([
                'status' => SupportSession::STATUS_IN_PROGRESS,
                'last_message_at' => now(),
            ])->save();
        }

        return back()->with($flashType, $flashMessage);
    }

    public function updateStatus(Request $request, SupportTicket $ticket, TelegramSupportBotService $bot)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::statusOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update(['status' => $data['status']]);

        if ($data['status'] === SupportTicket::STATUS_CLOSED) {
            $this->closeTicketAndNotify($ticket, $bot, auth()->id(), $data['note'] ?? null);
        } else {
            $this->syncSessionStatus($ticket);
        }

        return back()->with('success', __('portal.saved'));
    }

    public function togglePm(Request $request, SupportTicket $ticket)
    {
        $ticket->forceFill([
            'sent_to_pm' => ! $ticket->sent_to_pm,
            'sent_to_pm_at' => ! $ticket->sent_to_pm ? now() : null,
            'sent_to_pm_by_user_id' => ! $ticket->sent_to_pm ? auth()->id() : null,
        ])->save();

        return back()->with('success', $ticket->sent_to_pm ? 'Позначено як PM' : 'PM-позначку знято');
    }

    protected function syncSessionStatus(SupportTicket $ticket): void
    {
        $session = $ticket->session;

        if (! $session) {
            return;
        }

        $hasOpenTickets = $session->tickets()
            ->whereIn('status', [
                SupportTicket::STATUS_NEW,
                SupportTicket::STATUS_IN_PROGRESS,
            ])
            ->exists();

        $session->forceFill([
            'status' => $hasOpenTickets ? SupportSession::STATUS_IN_PROGRESS : SupportSession::STATUS_CLOSED,
            'closed_at' => $hasOpenTickets ? null : now(),
            'closed_by_user_id' => $hasOpenTickets ? null : auth()->id(),
        ])->save();

        if ($session->client) {
            $currentTicketId = $hasOpenTickets
                ? $session->tickets()
                    ->whereIn('status', [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS])
                    ->latest('updated_at')
                    ->value('id')
                : null;

            $session->client->forceFill([
                'current_support_ticket_id' => $currentTicketId,
                'current_support_session_id' => $session->id,
            ])->save();
        }
    }

    protected function closeTicketAndNotify(SupportTicket $ticket, TelegramSupportBotService $bot, ?int $userId, ?string $note = null): void
    {
        $ticket->loadMissing('client', 'session.topic');
        $topicLabel = $ticket->session?->topic?->displayLabel() ?? __('portal.empty');

        $ticket->forceFill([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $userId,
            'closed_note' => $note,
        ])->save();

        if ($ticket->client?->telegram_chat_id) {
            $bot->sendMessage($ticket->client->telegram_chat_id, __('portal.support.closed_message', [
                'number' => $ticket->number_in_session,
                'topic' => $topicLabel,
            ]));

            $this->requestRating($ticket, $bot);
        }

        $supportChatId = (string) ($ticket->session?->telegram_chat_id ?? '');
        $threadId = (int) ($ticket->session?->telegram_thread_id ?? 0);
        if ($supportChatId !== '' && $threadId > 0) {
            $bot->sendMessage($supportChatId, __('portal.support.closed_notice_admin', [
                'number' => $ticket->number_in_session,
                'topic' => $topicLabel,
            ]), [
                'message_thread_id' => $threadId,
            ]);
        }

        $this->syncSessionStatus($ticket);
    }

    protected function requestRating(SupportTicket $ticket, TelegramSupportBotService $bot): void
    {
        if (! $ticket->client?->telegram_chat_id) {
            return;
        }

        $buttons = collect(range(0, 5))->map(fn (int $score) => [[
            'text' => (string) $score,
            'callback_data' => 'ts:rate:' . $ticket->id . ':' . $score,
        ]])->values()->all();

        $bot->sendMessage($ticket->client->telegram_chat_id, __('portal.support.request.rating_prompt'), [
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ]);

        $bot->sendMessage($ticket->client->telegram_chat_id, __('portal.support.request.rating_comment_hint'));
    }

    protected function telegramFailureReason(array $response): string
    {
        $reason = trim((string) data_get($response, 'description', ''));

        if ($reason === '') {
            $reason = trim((string) data_get($response, 'error', 'Telegram request failed.'));
        }

        return Str::limit($reason, 120);
    }
}