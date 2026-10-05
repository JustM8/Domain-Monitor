<?php

namespace App\Modules\TelegramSupport\Services;

use App\Models\User;
use App\Modules\TelegramSupport\Models\SupportClient;
use App\Modules\TelegramSupport\Models\SupportMessage;
use App\Modules\TelegramSupport\Models\SupportSession;
use App\Modules\TelegramSupport\Models\SupportTicket;
use App\Modules\TelegramSupport\Models\SupportTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportUpdateHandler
{
    public function handle(array $payload, TelegramSupportBotService $bot)
    {
        $updateId = data_get($payload, 'update_id');
        $incomingMessage = data_get($payload, 'message', []);
        $incomingCallback = data_get($payload, 'callback_query', []);

        Log::debug('telegram_support.webhook.received', [
            'update_id' => $updateId,
            'has_message' => filled($incomingMessage),
            'has_callback' => filled($incomingCallback),
        ]);

        if ($incomingCallback) {
            $this->handleCallbackQuery($incomingCallback, $bot);

            return response()->json(['ok' => true]);
        }

        $message = $incomingMessage;
        if (! $message) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) data_get($message, 'chat.id');
        $telegramUserId = (string) data_get($message, 'from.id');
        $text = trim((string) data_get($message, 'text', ''));
        $contactPhone = trim((string) data_get($message, 'contact.phone_number', ''));
        $username = data_get($message, 'from.username');
        $firstName = data_get($message, 'from.first_name');
        $lastName = data_get($message, 'from.last_name');

        if ($chatId === '' || $telegramUserId === '') {
            return response()->json(['ok' => true]);
        }

        if ($this->isSupportGroupChat($chatId)) {
            $this->handleSupportGroupMessage($message, $bot);

            return response()->json(['ok' => true]);
        }

        if (data_get($message, 'chat.type') !== 'private') {
            return response()->json(['ok' => true]);
        }
        $client = SupportClient::query()->firstOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'telegram_chat_id' => $chatId,
                'telegram_username' => $username,
                'telegram_first_name' => $firstName,
                'telegram_last_name' => $lastName,
                'state' => 'await_company',
                'draft_data' => [],
                'last_active_at' => now(),
            ]
        );

        $client->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
            'telegram_first_name' => $firstName,
            'telegram_last_name' => $lastName,
            'last_active_at' => now(),
        ])->save();

        if ($this->isMenuAction($text)) {
            $this->sendMainMenu($bot, $client);

            return response()->json(['ok' => true]);
        }

        if ($this->isMyTicketsAction($text)) {
            $this->sendMyTickets($bot, $client);

            return response()->json(['ok' => true]);
        }

        if ($this->isStartAction($text) || $this->isNewRequestAction($text) || Str::startsWith($text, '/start') || Str::startsWith($text, '/new')) {
            $client->forceFill([
                'draft_data' => [],
            ])->save();

            if ($client->isReady()) {
                $this->sendTopicSelection($bot, $client);

                return response()->json(['ok' => true]);
            }

            $client->forceFill([
                'state' => 'await_company',
            ])->save();

            $this->sendCompanyPrompt($bot, $client);

            return response()->json(['ok' => true]);
        }
        if ($client->state === 'await_company') {
            $company = trim($text);
            if ($company === '') {
                $this->sendCompanyPrompt($bot, $client);

                return response()->json(['ok' => true]);
            }

            $client->forceFill([
                'company_name' => $company,
                'state' => 'await_name',
            ])->save();

            $this->sendNamePrompt($bot, $client);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'await_name') {
            if ($text === '') {
                $this->sendNamePrompt($bot, $client);

                return response()->json(['ok' => true]);
            }

            $client->forceFill([
                'full_name' => $text,
                'state' => 'await_email',
            ])->save();

            $bot->sendMessage($chatId, __('portal.support.request.email_hint'));

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'await_email') {
            if (! filter_var($text, FILTER_VALIDATE_EMAIL)) {
                $bot->sendMessage($chatId, __('portal.support.request.email_invalid'));

                return response()->json(['ok' => true]);
            }

            $client->forceFill([
                'email' => $text,
                'state' => 'await_phone',
            ])->save();

            $bot->sendMessage($chatId, __('portal.support.request.phone_hint'), [
                'reply_markup' => [
                    'keyboard' => [[['text' => __('portal.support.share_phone'), 'request_contact' => true]]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ],
            ]);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'await_phone') {
            $phone = $contactPhone !== '' ? $contactPhone : $text;

            if ($phone === '') {
                $this->sendPhonePrompt($bot, $client);

                return response()->json(['ok' => true]);
            }

            $client->forceFill([
                'phone' => $phone,
                'state' => 'await_position',
            ])->save();

            $bot->sendMessage($chatId, __('portal.support.request.position_hint'), [
                'reply_markup' => $this->clientReplyKeyboard(),
            ]);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'await_position') {
            $client->forceFill([
                'position' => $text !== '' && $text !== '/skip' ? $text : null,
                'state' => 'ready',
            ])->save();

            $this->sendMainMenu($bot, $client);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'await_rating_comment') {
            $draft = is_array($client->draft_data) ? $client->draft_data : [];
            $ticketId = (int) ($draft['ticket_id'] ?? 0);
            $rating = isset($draft['rating']) ? (int) $draft['rating'] : null;
            $ticket = $ticketId ? SupportTicket::query()->with('client')->find($ticketId) : null;

            if ($ticket && $rating !== null) {
                $ticket->forceFill([
                    'customer_rating' => $rating,
                    'customer_rating_comment' => $text !== '' && $text !== '/skip' ? $text : null,
                    'customer_rated_at' => now(),
                ])->save();
            }

            $client->forceFill([
                'state' => 'ready',
                'draft_data' => [],
            ])->save();

            $bot->sendMessage($chatId, __('portal.saved'));
            $this->sendMainMenu($bot, $client);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'ready' && Str::startsWith($text, '/new')) {
            $this->sendTopicSelection($bot, $client);

            return response()->json(['ok' => true]);
        }

        $pendingTicket = $client->pendingSupportTicket()->with(['session.topic', 'client'])->first();
        $currentTicket = $client->currentTicket()->with(['session.topic', 'client'])->first();
        $ticketToProcess = null;

        if ($pendingTicket && ! $pendingTicket->isClosed()) {
            $ticketToProcess = $pendingTicket;
        } elseif ($currentTicket && ! $currentTicket->isClosed()) {
            $ticketToProcess = $currentTicket;
        }

        if ($client->state === 'ready' && $ticketToProcess) {
            $this->forwardClientMessageToSupport($client, $ticketToProcess, $message, $bot);

            return response()->json(['ok' => true]);
        }

        if ($client->state === 'ready') {
            $this->sendMainMenu($bot, $client);
        }

        return response()->json(['ok' => true]);
    }

    protected function handleCallbackQuery(array $callback, TelegramSupportBotService $bot): void
    {
        $callbackId = (string) data_get($callback, 'id');
        $data = (string) data_get($callback, 'data', '');
        $message = data_get($callback, 'message', []);
        $chatId = (string) data_get($message, 'chat.id');
        $telegramUserId = (string) data_get(data_get($callback, 'from', []), 'id');

        if (Str::startsWith($data, 'ts:close:')) {
            $ticketId = (int) Str::afterLast($data, ':');
            $ticket = SupportTicket::query()->with('session.topic')->find($ticketId);

            if ($ticket && $ticket->session?->telegram_chat_id === $chatId) {
                $bot->answerCallbackQuery($callbackId, __('portal.support.close'));
                $this->closeTicketFromTelegram($ticket, $bot, $this->verifiedPortalUserForTelegramId($telegramUserId));

                return;
            }

            $bot->answerCallbackQuery($callbackId, __('portal.support.request.failed'));

            return;
        }

        $client = SupportClient::query()->where('telegram_user_id', $telegramUserId)->first();
        if (! $client) {
            return;
        }

        try {
            if ($data === 'ts:start' || $data === 'ts:new') {
                $client->forceFill(['draft_data' => []])->save();

                if ($client->isReady()) {
                    $bot->answerCallbackQuery($callbackId, __('portal.support.request.processing'));
                    $this->sendTopicSelection($bot, $client);

                    return;
                }

                $client->forceFill(['state' => 'await_company'])->save();
                $bot->answerCallbackQuery($callbackId, __('portal.support.request.complete_profile'));
                $this->sendCompanyPrompt($bot, $client);

                return;
            }

            if (Str::startsWith($data, 'ts:topic:')) {
                $topicId = (int) Str::afterLast($data, ':');
                $topic = SupportTopic::query()->where('is_active', true)->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->find($topicId);

                if (! $topic) {
                    $bot->answerCallbackQuery($callbackId, __('portal.support.no_topics'));

                    return;
                }

                if (! $client->isReady()) {
                    $bot->answerCallbackQuery($callbackId, __('portal.support.request.complete_profile'));
                    $client->forceFill(['state' => 'await_company', 'draft_data' => []])->save();
                    $this->sendCompanyPrompt($bot, $client);

                    return;
                }

                $bot->answerCallbackQuery($callbackId, __('portal.support.request.processing'));
                $result = $this->openTopicSession($client, $topic, $bot);

                if (! ($result['created'] ?? true)) {
                    $bot->sendMessage($client->telegram_chat_id, __('portal.support.topic_reused'));
                } else {
                    $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.opened_topic', [
                        'number' => $result['ticket']->number_in_session ?? $result['ticket']->displayLabel(),
                        'topic' => $topic->displayLabel(),
                    ]));
                }

                $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.continue_hint'));

                return;
            }

            if (Str::startsWith($data, 'ts:ticket:')) {
                $ticketId = (int) Str::afterLast($data, ':');
                $ticket = SupportTicket::query()->with(['session.topic', 'messages'])->find($ticketId);

                if ($ticket && (int) $ticket->support_client_id === (int) $client->id) {
                    $bot->answerCallbackQuery($callbackId, __('portal.support.ticket_details'));
                    $this->sendTicketOpeningMessage($bot, $client, $ticket);
                }

                return;
            }

            if (Str::startsWith($data, 'ts:rate:')) {
                [, , $ticketId, $score] = array_pad(explode(':', $data), 4, null);
                $ticket = SupportTicket::query()->find((int) $ticketId);

                if ($ticket && (int) $ticket->support_client_id === (int) $client->id) {
                    $ticket->forceFill([
                        'customer_rating' => (int) $score,
                        'customer_rated_at' => now(),
                    ])->save();

                    $client->forceFill([
                        'state' => 'await_rating_comment',
                        'draft_data' => [
                            'ticket_id' => $ticket->id,
                            'rating' => (int) $score,
                        ],
                    ])->save();

                    $bot->answerCallbackQuery($callbackId, __('portal.support.rating'));
                    $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.rating_comment_hint'));

                    return;
                }
            }

        } catch (\Throwable $e) {
            Log::error('telegram_support.callback.failed', [
                'callback_id' => $callbackId,
                'data' => $data,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $bot->answerCallbackQuery($callbackId, __('portal.support.request.failed'));
        }
    }

    protected function openTopicSession(SupportClient $client, SupportTopic $topic, TelegramSupportBotService $bot): array
    {
        $supportChatId = (string) $topic->telegram_chat_id;

        Log::info('telegram_support.session.open.start', [
            'client_id' => $client->id,
            'topic_id' => $topic->id,
            'topic_code' => $topic->code,
            'support_chat_id' => $supportChatId,
        ]);

        $result = DB::transaction(function () use ($client, $topic, $supportChatId) {
            $session = SupportSession::query()->firstOrCreate(
                [
                    'support_client_id' => $client->id,
                    'support_topic_id' => $topic->id,
                ],
                [
                    'number' => 'TMP-'.Str::upper(Str::random(10)),
                    'title' => $topic->displayLabel(),
                    'status' => SupportSession::STATUS_OPEN,
                    'assigned_role' => $topic->assigned_role,
                    'telegram_chat_id' => $supportChatId,
                    'last_message_at' => now(),
                ]
            );

            if ($session->telegram_chat_id !== $supportChatId) {
                $session->forceFill([
                    'telegram_chat_id' => $supportChatId,
                ])->save();
            }

            $activeTicket = $session->tickets()
                ->lockForUpdate()
                ->whereIn('status', [
                    SupportTicket::STATUS_NEW,
                    SupportTicket::STATUS_IN_PROGRESS,
                ])
                ->latest('updated_at')
                ->first();

            if ($activeTicket) {
                $session->forceFill([
                    'status' => SupportSession::STATUS_OPEN,
                    'assigned_role' => $topic->assigned_role,
                    'last_message_at' => now(),
                ])->save();

                return [
                    'created' => false,
                    'session' => $session,
                    'ticket' => $activeTicket,
                ];
            }

            if ($session->status === SupportSession::STATUS_CLOSED) {
                $session->forceFill([
                    'status' => SupportSession::STATUS_OPEN,
                    'closed_at' => null,
                    'closed_by_user_id' => null,
                    'closed_note' => null,
                ])->save();
            }

            $sessionFresh = SupportSession::query()->lockForUpdate()->findOrFail($session->id);
            $nextNumber = ((int) $sessionFresh->tickets()->lockForUpdate()->max('number_in_session')) + 1;

            $ticket = SupportTicket::create([
                'support_client_id' => $client->id,
                'support_session_id' => $sessionFresh->id,
                'number' => 'TMP-'.Str::upper(Str::random(12)),
                'number_in_session' => $nextNumber,
                'type' => $topic->code,
                'subject' => $topic->displayLabel(),
                'description' => null,
                'status' => SupportTicket::STATUS_NEW,
                'assigned_role' => $topic->assigned_role,
                'topic_thread_id' => $sessionFresh->telegram_thread_id,
            ]);

            $ticket->forceFill([
                'number' => 'SUP-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
            ])->save();

            $sessionFresh->forceFill([
                'status' => SupportSession::STATUS_OPEN,
                'assigned_role' => $topic->assigned_role,
                'last_message_at' => now(),
            ])->save();

            return [
                'created' => true,
                'session' => $sessionFresh->fresh(['client', 'topic']),
                'ticket' => $ticket->fresh(['session', 'client']),
            ];
        });

        $session = $result['session'];
        $ticket = $result['ticket'];

        Log::info('telegram_support.session.resolved', [
            'client_id' => $client->id,
            'topic_id' => $topic->id,
            'session_id' => $session->id,
            'session_number' => $session->number,
            'session_status' => $session->status,
            'telegram_thread_id' => $session->telegram_thread_id,
        ]);

        if (blank($session->telegram_thread_id) && filled($supportChatId)) {
            $topicName = $this->forumTopicName($client, $topic, $session);
            Log::info('telegram_support.forum_topic.create_request', [
                'session_id' => $session->id,
                'topic_id' => $topic->id,
                'topic_name' => $topicName,
                'support_chat_id' => $supportChatId,
            ]);

            $response = $bot->createForumTopic($supportChatId, $topicName);

            Log::info('telegram_support.forum_topic.created', [
                'session_id' => $session->id,
                'topic_id' => $topic->id,
                'topic_name' => $topicName,
                'support_chat_id' => $supportChatId,
                'response_ok' => (bool) data_get($response, 'ok'),
                'response' => $response,
            ]);

            if (data_get($response, 'ok')) {
                $session->forceFill([
                    'telegram_thread_id' => data_get($response, 'result.message_thread_id'),
                ])->save();
            } else {
                Log::warning('telegram_support.forum_topic.failed', [
                    'session_id' => $session->id,
                    'topic_id' => $topic->id,
                    'topic_name' => $topicName,
                    'support_chat_id' => $supportChatId,
                    'response' => $response,
                ]);
            }
        } elseif (blank($supportChatId)) {
            Log::warning('telegram_support.forum_topic.skipped_missing_support_chat', [
                'session_id' => $session->id,
                'topic_id' => $topic->id,
            ]);
        }

        if (($result['created'] ?? false)) {
            Log::info('telegram_support.ticket.create_new', [
                'session_id' => $session->id,
                'client_id' => $client->id,
                'topic_id' => $topic->id,
                'ticket_id' => $ticket->id,
            ]);
        } else {
            Log::info('telegram_support.ticket.reuse_active', [
                'session_id' => $session->id,
                'client_id' => $client->id,
                'topic_id' => $topic->id,
                'ticket_id' => $ticket->id,
            ]);
        }

        $client->forceFill([
            'current_support_session_id' => $session->id,
            'current_support_ticket_id' => $ticket->id,
            'pending_support_ticket_id' => ($result['created'] ?? false) ? $ticket->id : null,
            'state' => 'ready',
            'draft_data' => [],
            'last_active_at' => now(),
        ])->save();

        return [
            'created' => (bool) ($result['created'] ?? false),
            'session' => $session,
            'ticket' => $ticket,
        ];
    }

    protected function forwardClientMessageToSupport(SupportClient $client, SupportTicket $ticket, array $message, TelegramSupportBotService $bot): void
    {
        $session = $ticket->session()->with(['topic', 'client'])->first();
        if (! $session) {
            return;
        }

        $supportChatId = (string) ($session->telegram_chat_id ?: $session->topic?->telegram_chat_id ?: $ticket->session?->telegram_chat_id);
        $summary = $this->extractMessageSummary($message);
        $payload = $this->extractAttachmentPayload($message);
        $isFirstClientMessage = $ticket->last_client_message_at === null;

        $storedMessage = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'direction' => 'client',
            'body' => $summary,
            'payload' => array_filter([
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'position' => $client->position,
                'client_message_id' => (int) data_get($message, 'message_id'),
                'attachment' => $payload,
            ]),
            'telegram_chat_id' => $client->telegram_chat_id,
            'telegram_username' => $client->telegram_username,
        ]);

        $ticket->forceFill([
            'last_client_message_at' => now(),
        ])->save();

        $this->touchOpenThreadState($client, $ticket, SupportSession::STATUS_IN_PROGRESS);

        if ((int) $client->pending_support_ticket_id === (int) $ticket->id) {
            $client->forceFill(['pending_support_ticket_id' => null])->save();
        }

        if (blank(
            $session->telegram_thread_id) && filled($supportChatId)) {
            $this->recreateSupportForumThread($session, $bot, $supportChatId);
            $session->refresh();
        }

        if ($isFirstClientMessage) {
            $this->announceOpenedTicket($client, $session, $ticket, $bot);
        }

        if (filled($supportChatId) && filled($session->telegram_thread_id)) {
            $headerText = $this->supportClientMessageHeader($client);
            $bot->sendMessage($supportChatId, $headerText, [
                'message_thread_id' => (int) $session->telegram_thread_id,
            ]);

            $response = $bot->copyMessage(
                $supportChatId,
                $client->telegram_chat_id,
                (int) data_get($message, 'message_id'),
                ['message_thread_id' => (int) $session->telegram_thread_id]
            );

            if (! data_get($response, 'ok')) {
                Log::warning('telegram_support.forward.copy_failed', [
                    'ticket_id' => $ticket->id,
                    'session_id' => $session->id,
                    'support_chat_id' => $supportChatId,
                    'thread_id' => $session->telegram_thread_id,
                    'description' => data_get($response, 'description'),
                ]);

                $bot->sendMessage($supportChatId, $summary, [
                    'message_thread_id' => (int) $session->telegram_thread_id,
                ]);
            }

            if (filled(data_get($response, 'result.message_id'))) {
                $storedMessage->forceFill(['telegram_message_id' => (string) data_get($response, 'result.message_id')])->save();
            }

        }
    }

    protected function touchOpenThreadState(SupportClient $client, SupportTicket $ticket, string $sessionStatus): void
    {
        if (in_array($ticket->status, [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS], true)) {
            $ticket->forceFill([
                'status' => SupportTicket::STATUS_IN_PROGRESS,
            ])->save();
        }

        if ($ticket->session) {
            $ticket->session->forceFill([
                'status' => $sessionStatus,
                'last_message_at' => now(),
                'assigned_role' => $ticket->session->assigned_role ?: $ticket->assigned_role,
            ])->save();
        }

        $client->forceFill([
            'current_support_session_id' => $ticket->support_session_id,
            'current_support_ticket_id' => $ticket->id,
            'last_active_at' => now(),
        ])->save();
    }

    protected function supportClientMessageHeader(SupportClient $client): string
    {
        $displayName = trim((string) $client->full_name)
            ?: trim((string) $client->telegram_first_name.' '.(string) $client->telegram_last_name)
            ?: (string) $client->telegram_username
            ?: __('portal.empty');

        $company = $client->companyDisplayName();
        $username = ltrim((string) $client->telegram_username, '@');
        $nickname = filled($username) && strcasecmp($displayName, $username) !== 0
            ? '
<code>'.e('@'.$username).'</code>'
            : '';

        return implode('
', array_filter([
            '👤 <b>'.e($displayName).'</b>',
            filled($company) ? '🏢 <b>'.e($company).'</b>' : null,
            $nickname ? trim($nickname) : null,
        ]));
    }

    protected function handleSupportGroupMessage(array $message, TelegramSupportBotService $bot): void
    {
        if (data_get($message, 'from.is_bot')) {
            return;
        }

        $threadId = data_get($message, 'message_thread_id');
        if (! $threadId) {
            return;
        }

        $session = SupportSession::query()
            ->with(['client', 'topic', 'activeTicket'])
            ->where('telegram_chat_id', (string) data_get($message, 'chat.id'))
            ->where('telegram_thread_id', (int) $threadId)
            ->first();

        if (! $session || ! $session->client?->telegram_chat_id) {
            return;
        }

        $sender = $this->verifiedPortalUserForTelegramMessage($message);
        $text = trim((string) data_get($message, 'text', ''));
        if ($this->textMatches($text, [__('portal.support.close'), '/close'])) {
            $ticket = $session->activeTicket ?? $session->tickets()
                ->whereIn('status', [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS])
                ->latest('updated_at')
                ->first() ?? $session->tickets()->latest('id')->first();

            if ($ticket) {
                $this->closeTicketFromTelegram($ticket, $bot, $sender);
            }

            return;
        }

        $replyToMessageId = data_get($message, 'reply_to_message.message_id');
        $ticket = null;

        if ($replyToMessageId) {
            $sourceMessage = SupportMessage::query()->with('ticket')->where('telegram_message_id', (string) $replyToMessageId)->first();
            if ($sourceMessage?->ticket?->support_session_id === $session->id) {
                $ticket = $sourceMessage->ticket;
            }
        }

        $ticket ??= $session->activeTicket ?? $session->tickets()
            ->whereIn('status', [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS])
            ->latest('updated_at')
            ->first() ?? $session->tickets()->latest('id')->first();

        if (! $ticket) {
            return;
        }

        $sourceId = (string) data_get($message, 'message_id');
        $sourceChat = (string) data_get($message, 'chat.id');
        $existing = SupportMessage::query()->where('support_ticket_id', $ticket->id)->where('direction', 'staff')
            ->where('payload->source_message_id', $sourceId)->where('payload->source_chat_id', $sourceChat)->first();
        $reply = $existing ?: SupportMessage::create([
            'support_ticket_id' => $ticket->id, 'direction' => 'staff', 'body' => $this->extractMessageSummary($message),
            'payload' => [
                'source_message_id' => $sourceId,
                'source_chat_id' => $sourceChat,
                'source_user_id' => data_get($message, 'from.id'),
                'source_username' => data_get($message, 'from.username'),
                'source_first_name' => data_get($message, 'from.first_name'),
                'source_last_name' => data_get($message, 'from.last_name'),
            ],
            'telegram_username' => data_get($message, 'from.username'),
            'sent_by_user_id' => $sender?->id,
            'delivery_status' => 'pending',
        ]);
        app(SupportReplyDelivery::class)->deliver($reply);
    }

    protected function sendTicketOpeningMessage(TelegramSupportBotService $bot, SupportClient $client, SupportTicket $ticket): void
    {
        $ticket->loadMissing('session.topic', 'messages');
        $topicLabel = $ticket->session?->topic?->displayLabel() ?? __('portal.empty');
        $firstClientMessage = $ticket->messages->first(function (SupportMessage $message) {
            return $message->direction === 'client';
        });
        $replyToMessageId = (int) data_get($firstClientMessage?->payload, 'client_message_id', 0);

        $title = $ticket->displayLabel().' ('.$topicLabel.')';
        $lines = [
            '🧾 <b>'.e($title).'</b>',
            '🏷 Тема: '.e($topicLabel),
            '📍 Статус: '.e($ticket->statusLabel()),
        ];

        if ($firstClientMessage && filled($firstClientMessage->body)) {
            $snippet = Str::limit(trim((string) $firstClientMessage->body), 180);
            $lines[] = '';
            $lines[] = '💬 <b>Перше повідомлення:</b>';
            $lines[] = e($snippet);
        }

        $options = [
            'reply_markup' => $this->clientReplyKeyboard(),
        ];

        if ($replyToMessageId > 0) {
            $options['reply_to_message_id'] = $replyToMessageId;
            $options['allow_sending_without_reply'] = true;
        }

        $bot->sendMessage($client->telegram_chat_id, implode('
', $lines), $options);
    }

    protected function sendTicketSummary(TelegramSupportBotService $bot, SupportClient $client, SupportTicket $ticket): void
    {
        $this->sendTicketOpeningMessage($bot, $client, $ticket);
    }

    protected function closeTicketFromTelegram(SupportTicket $ticket, TelegramSupportBotService $bot, ?User $closedBy = null): void
    {
        $ticket->loadMissing('client', 'session.topic');
        if (! $ticket->client?->telegram_chat_id) {
            return;
        }

        if (! $ticket->isClosed()) {
            $ticket->forceFill([
                'status' => SupportTicket::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $closedBy?->id,
            ])->save();
        }

        if ($ticket->session) {
            $ticket->session->forceFill([
                'status' => SupportSession::STATUS_IN_PROGRESS,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ])->save();
        }

        $bot->sendMessage($ticket->client->telegram_chat_id, __('portal.support.closed_message', [
            'number' => $ticket->number_in_session,
            'topic' => $ticket->session?->topic?->displayLabel() ?? __('portal.empty'),
        ]));

        $supportChatId = (string) ($ticket->session?->telegram_chat_id ?? '');
        $threadId = (int) ($ticket->session?->telegram_thread_id ?? 0);
        if ($supportChatId !== '' && $threadId > 0) {
            $bot->sendMessage($supportChatId, __('portal.support.closed_notice_admin', [
                'number' => $ticket->number_in_session,
                'topic' => $ticket->session?->topic?->displayLabel() ?? __('portal.empty'),
            ]), [
                'message_thread_id' => $threadId,
            ]);
        }

        $buttons = collect(range(0, 5))->map(fn (int $score) => [[
            'text' => (string) $score,
            'callback_data' => 'ts:rate:'.$ticket->id.':'.$score,
        ]])->values()->all();

        $bot->sendMessage($ticket->client->telegram_chat_id, __('portal.support.request.rating_prompt'), [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }
    protected function verifiedPortalUserForTelegramMessage(array $message): ?User
    {
        return $this->verifiedPortalUserForTelegramId((string) data_get($message, 'from.id'));
    }

    protected function verifiedPortalUserForTelegramId(string $telegramUserId): ?User
    {
        if ($telegramUserId === '') {
            return null;
        }

        return User::query()
            ->where('telegram_chat_id', $telegramUserId)
            ->whereNotNull('telegram_verified_at')
            ->where('is_active', true)
            ->where('approval_status', 'active')
            ->first();
    }
    protected function sendCompanyPrompt(TelegramSupportBotService $bot, SupportClient $client): void
    {
        Log::info('telegram_support.prompt.company', [
            'client_id' => $client->id,
        ]);

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.company_hint'), [
            'reply_markup' => $this->clientReplyKeyboard(),
        ]);
    }

    protected function sendNamePrompt(TelegramSupportBotService $bot, SupportClient $client): void
    {
        Log::info('telegram_support.prompt.name', [
            'client_id' => $client->id,
        ]);

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.name_hint'), [
            'reply_markup' => $this->clientReplyKeyboard(),
        ]);
    }

    protected function sendPhonePrompt(TelegramSupportBotService $bot, SupportClient $client): void
    {
        Log::info('telegram_support.prompt.phone', [
            'client_id' => $client->id,
        ]);

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.phone_hint'), [
            'reply_markup' => $this->clientReplyKeyboard(true),
        ]);
    }

    protected function sendTopicSelection(TelegramSupportBotService $bot, SupportClient $client): void
    {
        $topics = SupportTopic::query()
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->orderBy('sort_order')
            ->get();

        Log::info('telegram_support.prompt.topic_selection', [
            'client_id' => $client->id,
            'topic_count' => $topics->count(),
            'topics' => $topics->map(function (SupportTopic $topic) {
                return [
                    'id' => $topic->id,
                    'code' => $topic->code,
                    'label' => $topic->displayLabel(),
                ];
            })->values()->all(),
        ]);

        if ($topics->isEmpty()) {
            $bot->sendMessage($client->telegram_chat_id, __('portal.support.no_topics'));

            return;
        }

        $buttons = $topics->map(function (SupportTopic $topic) {
            return [[
                'text' => $topic->displayLabel(),
                'callback_data' => 'ts:topic:'.$topic->id,
            ]];
        })->values()->all();

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.topic_hint'), [
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ]);
    }

    protected function sendMainMenu(TelegramSupportBotService $bot, SupportClient $client): void
    {
        $bot->sendMessage($client->telegram_chat_id, __('portal.support.menu_hint', [
            'name' => $client->displayName(),
        ]), [
            'reply_markup' => $this->clientReplyKeyboard(),
        ]);
    }

    protected function clientReplyKeyboard(bool $includeContact = false): array
    {
        $keyboard = [
            [
                ['text' => __('portal.support.menu_new')],
                ['text' => __('portal.support.my_tickets')],
            ],
            [
                ['text' => __('portal.support.start')],
                ['text' => __('portal.support.menu')],
            ],
        ];

        if ($includeContact) {
            array_unshift($keyboard, [[
                'text' => __('portal.support.share_phone'),
                'request_contact' => true,
            ]]);
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function textMatches(string $text, array $needles): bool
    {
        $value = mb_strtolower(trim($text));

        foreach ($needles as $needle) {
            if ($value === mb_strtolower(trim((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    protected function isStartAction(string $text): bool
    {
        return $this->textMatches($text, [__('portal.support.start'), '/start']);
    }

    protected function isMenuAction(string $text): bool
    {
        return $this->textMatches($text, [__('portal.support.menu'), '/menu']);
    }

    protected function isNewRequestAction(string $text): bool
    {
        return $this->textMatches($text, [__('portal.support.menu_new'), '/new']);
    }

    protected function isMyTicketsAction(string $text): bool
    {
        return $this->textMatches($text, [__('portal.support.my_tickets')]);
    }

    protected function telegramMessageLink(string $chatId, int $messageId): ?string
    {
        if ($messageId <= 0 || $chatId === '') {
            return null;
        }

        if (! str_starts_with($chatId, '-100')) {
            return null;
        }

        return 'https://t.me/c/'.substr($chatId, 4).'/'.$messageId;
    }

    protected function sendMyTickets(TelegramSupportBotService $bot, SupportClient $client): void
    {
        $tickets = $client->tickets()->with(['session.topic', 'messages'])->latest('created_at')->limit(5)->get();

        if ($tickets->isEmpty()) {
            $bot->sendMessage($client->telegram_chat_id, __('portal.support.no_tickets'));

            return;
        }

        $buttons = $tickets->map(function (SupportTicket $ticket) {
            $topicLabel = $ticket->session?->topic?->displayLabel();
            $text = $ticket->displayLabel();

            if (filled($topicLabel)) {
                $text .= ' ('.$topicLabel.')';
            }

            return [[
                'text' => $text,
                'callback_data' => 'ts:ticket:'.$ticket->id,
            ]];
        })->values()->all();

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.my_tickets_hint'), [
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ]);
    }

    protected function announceOpenedTicket(SupportClient $client, SupportSession $session, SupportTicket $ticket, TelegramSupportBotService $bot): void
    {
        $supportChatId = (string) ($session->telegram_chat_id ?: $session->topic?->telegram_chat_id ?: $ticket->session?->telegram_chat_id);
        $topicLabel = $session->topic?->displayLabel() ?? __('portal.empty');

        Log::info('telegram_support.thread.open_notice', [
            'client_id' => $client->id,
            'ticket_id' => $ticket->id,
            'session_id' => $session->id,
            'thread_id' => $session->telegram_thread_id,
            'support_chat_id' => $supportChatId,
            'topic_label' => $topicLabel,
        ]);

        if (filled($supportChatId) && filled($session->telegram_thread_id)) {
            usleep(250000);

            $response = $bot->sendMessage($supportChatId, __('portal.support.new_topic_notice', [
                'number' => $ticket->number_in_session,
                'topic' => $topicLabel,
            ]), [
                'message_thread_id' => (int) $session->telegram_thread_id,
                'reply_markup' => [
                    'keyboard' => [[['text' => __('portal.support.close')]]],
                    'resize_keyboard' => true,
                    'is_persistent' => true,
                ],
            ]);

            if (! data_get($response, 'ok') && str_contains((string) data_get($response, 'description', ''), 'message thread not found')) {
                $this->recreateSupportForumThread($session, $bot, $supportChatId);

                Log::warning('telegram_support.thread.open_notice_retry', [
                    'client_id' => $client->id,
                    'ticket_id' => $ticket->id,
                    'session_id' => $session->id,
                    'thread_id' => $session->telegram_thread_id,
                ]);

                if (filled($session->telegram_thread_id)) {
                    usleep(750000);

                    $bot->sendMessage($supportChatId, __('portal.support.new_topic_notice', [
                        'number' => $ticket->number_in_session,
                        'topic' => $topicLabel,
                    ]), [
                        'message_thread_id' => (int) $session->telegram_thread_id,
                        'reply_markup' => [
                            'keyboard' => [[['text' => __('portal.support.close')]]],
                            'resize_keyboard' => true,
                            'is_persistent' => true,
                        ],
                    ]);
                }
            }
        }

        $bot->sendMessage($client->telegram_chat_id, __('portal.support.request.sent_confirmation', [
            'number' => $ticket->number_in_session,
            'topic' => $topicLabel,
        ]));
    }

    protected function forumTopicName(SupportClient $client, SupportTopic $topic, SupportSession $session): string
    {
        return Str::limit(trim(implode(' · ', array_filter([
            $topic->displayLabel(),
            $client->displayName(),
        ]))), 128, '');
    }

    protected function recreateSupportForumThread(SupportSession $session, TelegramSupportBotService $bot, string $supportChatId): void
    {
        if (blank($supportChatId)) {
            return;
        }

        $session->loadMissing(['client', 'topic']);
        if (! $session->topic) {
            return;
        }

        $topicName = $this->forumTopicName($session->client, $session->topic, $session);

        Log::warning('telegram_support.forum_topic.recreate_request', [
            'session_id' => $session->id,
            'topic_id' => $session->support_topic_id,
            'topic_name' => $topicName,
            'support_chat_id' => $supportChatId,
            'old_thread_id' => $session->telegram_thread_id,
        ]);

        $response = $bot->createForumTopic($supportChatId, $topicName);

        Log::warning('telegram_support.forum_topic.recreated', [
            'session_id' => $session->id,
            'topic_id' => $session->support_topic_id,
            'topic_name' => $topicName,
            'support_chat_id' => $supportChatId,
            'response_ok' => (bool) data_get($response, 'ok'),
            'response' => $response,
        ]);

        if (data_get($response, 'ok')) {
            $session->forceFill([
                'telegram_thread_id' => data_get($response, 'result.message_thread_id'),
            ])->save();
        }
    }

    protected function extractMessageSummary(array $message): string
    {
        $text = trim((string) data_get($message, 'text', ''));
        if ($text !== '') {
            return $text;
        }

        $caption = trim((string) data_get($message, 'caption', ''));
        if ($caption !== '') {
            return $caption;
        }

        if ($payload = $this->extractAttachmentPayload($message)) {
            return strtoupper((string) data_get($payload, 'type', 'attachment'));
        }

        return __('portal.empty');
    }

    protected function extractAttachmentPayload(array $message): ?array
    {
        if ($photo = data_get($message, 'photo')) {
            $photo = is_array($photo) ? array_values($photo) : [];
            $best = $photo ? end($photo) : null;

            return [
                'type' => 'photo',
                'file_id' => data_get($best, 'file_id'),
                'file_unique_id' => data_get($best, 'file_unique_id'),
                'caption' => data_get($message, 'caption'),
            ];
        }

        if ($document = data_get($message, 'document')) {
            return [
                'type' => 'document',
                'file_id' => data_get($document, 'file_id'),
                'file_unique_id' => data_get($document, 'file_unique_id'),
                'file_name' => data_get($document, 'file_name'),
                'mime_type' => data_get($document, 'mime_type'),
                'caption' => data_get($message, 'caption'),
            ];
        }

        if ($video = data_get($message, 'video')) {
            return [
                'type' => 'video',
                'file_id' => data_get($video, 'file_id'),
                'file_unique_id' => data_get($video, 'file_unique_id'),
                'caption' => data_get($message, 'caption'),
            ];
        }

        return null;
    }

    protected function isSupportGroupChat(string $chatId): bool
    {
        $supportChatId = (string) config('telegram_support.support_chat_id');

        if (filled($supportChatId) && $supportChatId === $chatId) {
            return true;
        }

        return SupportTopic::query()
            ->where('is_active', true)
            ->where('telegram_chat_id', $chatId)
            ->exists();
    }
}
