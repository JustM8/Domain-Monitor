<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TelegramSupport\Models\SupportMessage;
use App\Modules\TelegramSupport\Models\SupportTicket;
use App\Modules\TelegramSupport\Models\SupportTopic;
use Illuminate\Support\Collection;

class SupportAnalyticsController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::query()->with(['session.topic.responsibleUsers', 'sentToPmBy']);

        $totals = [
            'all' => (clone $tickets)->count(),
            'open' => (clone $tickets)->where('status', SupportTicket::STATUS_NEW)->count(),
            'in_progress' => (clone $tickets)->where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
            'closed' => (clone $tickets)->where('status', SupportTicket::STATUS_CLOSED)->count(),
            'pm' => (clone $tickets)->where('sent_to_pm', true)->count(),
            'avg_rating' => (int) round((clone $tickets)->whereNotNull('customer_rating')->avg('customer_rating') ?? 0),
        ];

        $ratingBuckets = collect(range(0, 5))->mapWithKeys(function (int $score) use ($tickets) {
            return [$score => (clone $tickets)->where('customer_rating', $score)->count()];
        });

        $byTopic = SupportTopic::query()
            ->withCount(['sessions as tickets_count' => function ($query) {
                $query->selectRaw('count(*)');
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(function (SupportTopic $topic) use ($tickets) {
                $topicTickets = (clone $tickets)->whereHas('session', fn ($query) => $query->where('support_topic_id', $topic->id));

                return [
                    'label' => $topic->displayLabel(),
                    'responsible' => $topic->responsibleLabel(),
                    'tickets' => $topicTickets->count(),
                    'closed' => (clone $topicTickets)->where('status', SupportTicket::STATUS_CLOSED)->count(),
                    'pm' => (clone $topicTickets)->where('sent_to_pm', true)->count(),
                    'avg_rating' => (int) round((clone $topicTickets)->whereNotNull('customer_rating')->avg('customer_rating') ?? 0),
                ];
            });

        $byManager = $this->actualManagerRows();

        $topTopicChart = $byTopic->sortByDesc('tickets')->take(6)->values();
        $topManagerChart = $byManager->sortByDesc('tickets')->take(6)->values();

        $chartData = [
            'status' => [
                'labels' => ['Відкриті', 'В роботі', 'Закриті'],
                'values' => [
                    $totals['open'],
                    $totals['in_progress'],
                    $totals['closed'],
                ],
            ],
            'ratings' => [
                'labels' => array_map('strval', range(0, 5)),
                'values' => array_values($ratingBuckets->all()),
            ],
            'topics' => [
                'labels' => $topTopicChart->pluck('label')->values()->all(),
                'tickets' => $topTopicChart->pluck('tickets')->values()->all(),
                'closed' => $topTopicChart->pluck('closed')->values()->all(),
            ],
            'managers' => [
                'labels' => $topManagerChart->pluck('name')->values()->all(),
                'tickets' => $topManagerChart->pluck('tickets')->values()->all(),
                'closed' => $topManagerChart->pluck('closed')->values()->all(),
            ],
        ];

        return view('portal.support.analytics', [
            'totals' => $totals,
            'ratingBuckets' => $ratingBuckets,
            'byTopic' => $byTopic,
            'byManager' => $byManager,
            'chartData' => $chartData,
        ]);
    }

    protected function actualManagerRows(): Collection
    {
        $messages = SupportMessage::query()
            ->with(['sentBy', 'ticket.session.topic', 'ticket.closedBy'])
            ->where('direction', 'staff')
            ->get();

        $closedTickets = SupportTicket::query()
            ->with(['closedBy', 'session.topic', 'messages.sentBy'])
            ->whereNotNull('closed_by_user_id')
            ->get();

        $responsibleUsers = SupportTopic::query()
            ->with('responsibleUsers')
            ->get()
            ->pluck('responsibleUsers')
            ->flatten()
            ->unique('id')
            ->values();

        $userIds = $messages->pluck('sent_by_user_id')
            ->merge($closedTickets->pluck('closed_by_user_id'))
            ->filter()
            ->unique()
            ->values();

        $actionUsers = User::query()
            ->whereIn('id', $userIds)
            ->get();

        $rows = $responsibleUsers
            ->merge($actionUsers)
            ->unique('id')
            ->values()
            ->map(fn (User $user) => $this->actualManagerRow($user, $messages, $closedTickets));

        $unverified = $messages->whereNull('sent_by_user_id');
        if ($unverified->isNotEmpty()) {
            $tickets = $unverified->pluck('ticket')->filter()->unique('id')->values();
            $rows->push([
                'name' => 'Неверифіковані staff-відповіді',
                'telegram' => null,
                'topics' => $this->topicList($tickets),
                'tickets' => $tickets->count(),
                'replies' => $unverified->count(),
                'first_responses' => $this->firstResponseCount(null, $tickets),
                'closed' => 0,
                'pm' => $tickets->where('sent_to_pm', true)->count(),
                'avg_rating' => $this->averageRating($tickets),
                'avg_first_response' => $this->averageFirstResponseLabel(null, $tickets),
                'is_unverified' => true,
            ]);
        }

        return $rows->sortByDesc('tickets')->values();
    }

    protected function actualManagerRow(User $user, Collection $messages, Collection $closedTickets): array
    {
        $managerMessages = $messages->where('sent_by_user_id', $user->id);
        $messageTickets = $managerMessages->pluck('ticket')->filter();
        $managerClosedTickets = $closedTickets->where('closed_by_user_id', $user->id);
        $tickets = $messageTickets->merge($managerClosedTickets)->unique('id')->values();

        return [
            'name' => $user->displayName(),
            'telegram' => $user->telegram_username ? '@'.ltrim($user->telegram_username, '@') : null,
            'topics' => $this->managerTopicList($user, $tickets),
            'tickets' => $tickets->count(),
            'replies' => $managerMessages->count(),
            'first_responses' => $this->firstResponseCount($user->id, $tickets),
            'closed' => $managerClosedTickets->count(),
            'pm' => $tickets->where('sent_to_pm', true)->count(),
            'avg_rating' => $this->averageRating($tickets),
            'avg_first_response' => $this->averageFirstResponseLabel($user->id, $tickets),
            'is_unverified' => false,
        ];
    }

    protected function managerTopicList(User $user, Collection $tickets): string
    {
        $assignedTopics = SupportTopic::query()
            ->whereHas('responsibleUsers', fn ($query) => $query->where('users.id', $user->id))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SupportTopic $topic) => $topic->displayLabel());

        $workedTopics = $tickets
            ->map(fn (SupportTicket $ticket) => $ticket->session?->topic?->displayLabel())
            ->filter();

        $topics = $assignedTopics->merge($workedTopics)->unique()->values();

        return $topics->isNotEmpty() ? $topics->implode(', ') : __('portal.empty');
    }
    protected function topicList(Collection $tickets): string
    {
        $topics = $tickets
            ->map(fn (SupportTicket $ticket) => $ticket->session?->topic?->displayLabel())
            ->filter()
            ->unique()
            ->values();

        return $topics->isNotEmpty() ? $topics->implode(', ') : __('portal.empty');
    }

    protected function firstResponseCount(?int $userId, Collection $tickets): int
    {
        return $tickets->filter(fn (SupportTicket $ticket) => $this->firstStaffMessageBelongsTo($ticket, $userId))->count();
    }

    protected function averageFirstResponseLabel(?int $userId, Collection $tickets): string
    {
        $seconds = $tickets
            ->filter(fn (SupportTicket $ticket) => $ticket->first_response_at && $this->firstStaffMessageBelongsTo($ticket, $userId))
            ->map(fn (SupportTicket $ticket) => max(0, $ticket->created_at?->diffInSeconds($ticket->first_response_at) ?? 0));

        if ($seconds->isEmpty()) {
            return __('portal.empty');
        }

        return $this->durationLabel((int) round($seconds->avg()));
    }

    protected function firstStaffMessageBelongsTo(SupportTicket $ticket, ?int $userId): bool
    {
        $ticket->loadMissing('messages');
        $firstStaffMessage = $ticket->messages->first(fn (SupportMessage $message) => $message->direction === 'staff');

        if (! $firstStaffMessage) {
            return false;
        }

        return $userId === null
            ? $firstStaffMessage->sent_by_user_id === null
            : (int) $firstStaffMessage->sent_by_user_id === $userId;
    }

    protected function averageRating(Collection $tickets): int
    {
        $ratings = $tickets->pluck('customer_rating')->filter(fn ($rating) => $rating !== null);

        return (int) round($ratings->avg() ?? 0);
    }

    protected function durationLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' с';
        }

        if ($seconds < 3600) {
            return round($seconds / 60).' хв';
        }

        return round($seconds / 3600, 1).' год';
    }
}
