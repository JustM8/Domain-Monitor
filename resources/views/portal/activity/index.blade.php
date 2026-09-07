@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.activity_log') }}</h2>
            <div class="portal-soft small">{{ __('portal.recent_activity_hint') }}</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
                <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
            </form>
            <span class="portal-chip">
                <i class="bi bi-journal-text"></i>
                <span>{{ $logs->total() }}</span>
            </span>
        </div>
    </div>
</div>

<div class="portal-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>{{ __('portal.when') }}</th>
                <th>{{ __('portal.by_whom') }}</th>
                <th>{{ __('portal.action') }}</th>
                <th>{{ __('portal.subject') }}</th>
                <th>{{ __('portal.details') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                    <td>
                        <div class="fw-semibold">{{ $log->user?->displayName() ?? __('portal.system') }}</div>
                        <div class="small portal-soft">{{ $log->user?->email ?? __('portal.empty') }}</div>
                    </td>
                    <td>
                        <span class="badge rounded-pill text-bg-light border">{{ $log->actionLabel() }}</span>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $log->subjectLabel() }}</div>
                        <div class="small portal-soft">{{ $log->subjectTypeLabel() }} #{{ $log->subject_id }}</div>
                    </td>
                    <td class="text-break">
                        {{ $log->changeSummary() ?? __('portal.empty') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="portal-soft">{{ __('portal.empty') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection
