@extends('layouts.portal')

@section('content')
@if($interruptedUpdates)
<div class="alert alert-warning">Перервані події Telegram: {{ $interruptedUpdates }}. Потрібна звірка адміністратором; автоматичне повторне надсилання зупинене, щоб уникнути дублів.</div>
@endif
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.support.tickets') }}</h2>
            <div class="portal-soft small">Коротка статистика по підтримці та список звернень.</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @can('support.read')<a class="btn btn-outline-secondary btn-sm" href="{{ route('portal.support.analytics') }}">Аналітика</a>@endcan
            <span class="portal-chip"><i class="bi bi-inboxes"></i><span>{{ $tickets->total() }}</span></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="portal-card p-3 h-100">
            <div class="portal-soft small">Відкриті</div>
            <div class="fs-4 fw-bold">{{ $stats['open'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="portal-card p-3 h-100">
            <div class="portal-soft small">В роботі</div>
            <div class="fs-4 fw-bold">{{ $stats['in_progress'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="portal-card p-3 h-100">
            <div class="portal-soft small">Закриті</div>
            <div class="fs-4 fw-bold">{{ $stats['closed'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="portal-card p-3 h-100">
            <div class="portal-soft small">Середня оцінка</div>
            <div class="fs-4 fw-bold">{{ $stats['avg_rating'] ?? 0 }}</div>
        </div>
    </div>
</div>

<div class="portal-card p-3 mb-3">
    <form method="GET" class="row g-2">
        <div class="col-lg-4">
            <input name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('portal.search') }}">
        </div>
        <div class="col-lg-2">
            <select name="status" class="form-select">
                <option value="">{{ __('portal.status') }}</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <select name="type" class="form-select">
                <option value="">{{ __('portal.support.topic') }}</option>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-6">
            <button class="btn btn-outline-secondary w-100">{{ __('portal.find') }}</button>
        </div>
    </form>
</div>

<div class="portal-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ __('portal.support.client') }}</th>
                <th>{{ __('portal.support.session') }}</th>
                <th>{{ __('portal.support.ticket') }}</th>
                <th>{{ __('portal.support.assigned') }}</th>
                <th>Час до першої відповіді</th>
                <th>Час до закриття</th>
                <th>Оцінка</th>
                <th>{{ __('portal.support.created_at') }}</th>
                <th>{{ __('portal.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                @php $lastMessage = $ticket->messages->last(); @endphp
                <tr>
                    <td class="text-nowrap">
                        <div class="fw-semibold">{{ $ticket->displayLabel() }}</div>
                        <div class="small portal-soft">{{ $ticket->session?->number ?? __('portal.empty') }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $ticket->client?->displayName() ?? __('portal.empty') }}</div>
                        <div class="small portal-soft">{{ $ticket->client?->email ?? __('portal.empty') }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $ticket->session?->number ?? __('portal.empty') }}</div>
                        <div class="small portal-soft">{{ $ticket->session?->topic?->displayLabel() ?? __('portal.empty') }}</div>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap mb-1">
                            <span class="badge rounded-pill text-bg-{{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                            <span class="badge rounded-pill text-bg-{{ $ticket->typeBadgeClass() }}">{{ $ticket->typeLabel() }}</span>
                            @if($ticket->sent_to_pm)
                                <span class="badge rounded-pill text-bg-dark">PM</span>
                            @endif
                        </div>
                        <div class="small fw-semibold text-truncate" style="max-width: 360px;">
                            {{ $ticket->subject }}
                        </div>
                        @if($lastMessage?->body)
                            <div class="small mt-1 text-truncate portal-soft" style="max-width: 360px;">
                                {{ \Illuminate\Support\Str::limit($lastMessage->body, 90) }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $ticket->responsibleLabel() }}</td>
                    <td>{{ $ticket->first_response_at ? $ticket->created_at->diffForHumans($ticket->first_response_at, true) : __('portal.empty') }}</td>
                    <td>{{ $ticket->closureSeconds() ? gmdate('H:i:s', $ticket->closureSeconds()) : __('portal.empty') }}</td>
                    <td>
                        <div class="fw-semibold">{{ $ticket->customer_rating ?? __('portal.empty') }}</div>
                        <div class="small portal-soft">{{ $ticket->customer_rating_comment ? \Illuminate\Support\Str::limit($ticket->customer_rating_comment, 40) : '' }}</div>
                    </td>
                    <td class="text-nowrap">{{ $ticket->created_at?->format('d.m.Y H:i') }}</td>
                    <td class="text-nowrap">
                        @can('support.read')<a class="btn btn-sm btn-primary" href="{{ route('portal.support.show', $ticket) }}">{{ __('portal.details') }}</a>@endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="portal-soft">{{ __('portal.empty') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
