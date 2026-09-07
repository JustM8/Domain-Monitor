<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TelegramSupport\Models\SupportTicket;
use App\Modules\TelegramSupport\Models\SupportTopic;

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

        $byManager = SupportTopic::query()
            ->with('responsibleUsers')
            ->orderBy('sort_order')
            ->get()
            ->flatMap(function (SupportTopic $topic) use ($tickets) {
                $topicTickets = (clone $tickets)->whereHas('session', fn ($query) => $query->where('support_topic_id', $topic->id));

                $rows = $topic->responsibleUsers->map(function ($user) use ($topicTickets, $topic) {
                    $managerTickets = (clone $topicTickets)->whereHas('session.topic.responsibleUsers', fn ($query) => $query->where('users.id', $user->id));

                    return [
                        'name' => $user->displayName(),
                        'topic' => $topic->displayLabel(),
                        'tickets' => $managerTickets->count(),
                        'closed' => (clone $managerTickets)->where('status', SupportTicket::STATUS_CLOSED)->count(),
                        'pm' => (clone $managerTickets)->where('sent_to_pm', true)->count(),
                        'avg_rating' => (int) round((clone $managerTickets)->whereNotNull('customer_rating')->avg('customer_rating') ?? 0),
                    ];
                })->values();

                if ($rows->isNotEmpty()) {
                    return $rows;
                }

                return collect([[
                    'name' => $topic->responsibleLabel(),
                    'topic' => $topic->displayLabel(),
                    'tickets' => $topicTickets->count(),
                    'closed' => (clone $topicTickets)->where('status', SupportTicket::STATUS_CLOSED)->count(),
                    'pm' => (clone $topicTickets)->where('sent_to_pm', true)->count(),
                    'avg_rating' => (int) round((clone $topicTickets)->whereNotNull('customer_rating')->avg('customer_rating') ?? 0),
                ]]);
            });

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
}
