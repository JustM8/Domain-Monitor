@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h1 class="h4 mb-0">{{ $ticket->displayLabel() }}</h1>
                <span class="badge rounded-pill text-bg-secondary">{{ $ticket->displayLabel() }}</span>
                <span class="badge rounded-pill text-bg-{{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                <span class="badge rounded-pill text-bg-{{ $ticket->typeBadgeClass() }}">{{ $ticket->typeLabel() }}</span>
                @if($ticket->sent_to_pm)
                    <span class="badge rounded-pill text-bg-dark">Надіслано PM</span>
                @endif
            </div>
            <div class="portal-soft">
                {{ $ticket->session?->number ?? __('portal.empty') }}
                ·
                {{ $ticket->session?->topic?->displayLabel() ?? __('portal.empty') }}
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('support.write')<form method="POST" action="{{ route('portal.support.status', $ticket) }}">
                @csrf
                <input type="hidden" name="status" value="{{ \App\Modules\TelegramSupport\Models\SupportTicket::STATUS_IN_PROGRESS }}">
                <button class="btn btn-outline-primary">{{ __('portal.support.status.in_progress') }}</button>
            </form>@endcan
            @can('support.write')<form method="POST" action="{{ route('portal.support.status', $ticket) }}">
                @csrf
                <input type="hidden" name="status" value="{{ \App\Modules\TelegramSupport\Models\SupportTicket::STATUS_CLOSED }}">
                <button class="btn btn-success">{{ __('portal.support.close') }}</button>
            </form>@endcan
            @can('support.write')<form method="POST" action="{{ route('portal.support.pm', $ticket) }}">
                @csrf
                <button class="btn {{ $ticket->sent_to_pm ? 'btn-dark' : 'btn-outline-dark' }}">{{ $ticket->sent_to_pm ? 'PM позначено' : 'Позначити PM' }}</button>
            </form>@endcan
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="portal-card p-4 h-100">
            <h2 class="h5 mb-3">{{ __('portal.support.session_details') }}</h2>
            <div class="vstack gap-3">
                <div>
                    <div class="portal-soft small">{{ __('portal.support.client') }}</div>
                    <div class="fw-semibold">{{ $ticket->client?->displayName() ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.company') }}</div>
                    <div class="fw-semibold">{{ $ticket->client?->companyDisplayName() ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.email') }}</div>
                    <div class="fw-semibold">{{ $ticket->client?->email ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.phone') }}</div>
                    <div class="fw-semibold">{{ $ticket->client?->phone ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.position') }}</div>
                    <div class="fw-semibold">{{ $ticket->client?->position ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.assigned') }}</div>
                    <div class="fw-semibold">{{ $ticket->responsibleLabel() }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.topic') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->topic?->displayLabel() ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.session') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->number ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.status_label') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->statusLabel() ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.requests') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->tickets?->count() ?? 0 }}</div>
                </div>
                <div>
                    <div class="portal-soft small">Час до першої відповіді</div>
                    <div class="fw-semibold">{{ $ticket->first_response_at ? $ticket->created_at->diffForHumans($ticket->first_response_at, true) : __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">Час до закриття</div>
                    <div class="fw-semibold">{{ $ticket->closureSeconds() ? gmdate('H:i:s', $ticket->closureSeconds()) : __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">Оцінка</div>
                    <div class="fw-semibold">{{ $ticket->customer_rating ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">Коментар</div>
                    <div class="fw-semibold text-break">{{ $ticket->customer_rating_comment ?: __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">Метрика якості</div>
                    <div class="fw-semibold">{{ $ticket->responseQualityScore() }} / 100</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.created_at') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->created_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div>
                </div>
                <div>
                    <div class="portal-soft small">{{ __('portal.support.updated_at') }}</div>
                    <div class="fw-semibold">{{ $ticket->session?->updated_at?->format('d.m.Y H:i') ?? __('portal.empty') }}</div>
                </div>
                @if($ticket->session?->closed_at)
                    <div>
                        <div class="portal-soft small">{{ __('portal.support.close') }}</div>
                        <div class="fw-semibold">{{ $ticket->session?->closed_at?->format('d.m.Y H:i') }}</div>
                    </div>
                @endif
                @if(filled($ticket->session?->closed_note))
                    <div>
                        <div class="portal-soft small">{{ __('portal.note') }}</div>
                        <div class="fw-semibold text-break">{{ $ticket->session?->closed_note }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="portal-card p-4 mt-3">
            <h2 class="h5 mb-3">{{ __('portal.support.requests') }}</h2>
            <div class="vstack gap-2">
                @forelse($ticket->session?->tickets ?? collect() as $requestItem)
                    @can('support.read')<a class="portal-surface p-3 text-decoration-none {{ $requestItem->id === $ticket->id ? 'border border-primary' : '' }}" href="{{ route('portal.support.show', $requestItem) }}">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <div class="fw-semibold">{{ $requestItem->displayLabel() }}</div>
                            <span class="badge rounded-pill text-bg-{{ $requestItem->statusBadgeClass() }}">{{ $requestItem->statusLabel() }}</span>
                        </div>
                        <div class="small portal-soft">{{ $requestItem->subject }}</div>
                    </a>@endcan
                @empty
                    <div class="portal-empty">{{ __('portal.empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="portal-card p-4 mb-3">
            <h2 class="h5 mb-3">{{ __('portal.support.messages') }}</h2>
            <div class="vstack gap-3">
                @forelse($ticket->messages as $message)
                    <div class="portal-surface p-3">
                        <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                            <div class="fw-semibold text-capitalize">{{ $message->direction }}</div>
                            <div class="small portal-soft">{{ $message->created_at?->format('d.m.Y H:i') }}</div>
                        </div>
                        @if($message->direction === 'staff')
                        <div class="small {{ $message->delivery_status === 'sent' ? 'text-success' : 'text-danger' }}">{{ ['sent' => 'Доставлено', 'pending' => 'Очікує доставки', 'failed' => 'Не доставлено'][$message->delivery_status] ?? $message->delivery_status }}</div>
                        @if($message->delivery_status !== 'sent')
                        <div class="small text-danger">{{ $message->delivery_error }}</div>
                        @can('support.reply')<form class="mt-2" method="POST" action="{{ route('portal.support.reply.retry', [$ticket, $message]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Повторити надсилання</button></form>@endcan
                        @endif
                        @endif
                        @if(filled($message->body))
                            <div class="text-break">{{ $message->body }}</div>
                        @endif
                        @if(filled(data_get($message->payload, 'attachment.caption')))
                            <div class="small portal-soft mt-2">
                                {{ data_get($message->payload, 'attachment.caption') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="portal-empty">{{ __('portal.empty') }}</div>
                @endforelse
            </div>
        </div>

        <div class="portal-card p-4">
            <h2 class="h5 mb-3">{{ __('portal.support.reply') }}</h2>
            @can('support.reply')<form method="POST" action="{{ route('portal.support.reply', $ticket) }}" class="vstack gap-3">
                @csrf
                <textarea name="body" class="form-control" rows="5" placeholder="{{ __('portal.support.message') }}" required></textarea>
                <div>
                    <button class="btn btn-primary">{{ __('portal.support.reply') }}</button>
                </div>
            </form>@endcan
        </div>
    </div>
</div>
@endsection
