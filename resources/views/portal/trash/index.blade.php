@extends('layouts.portal')

@section('content')
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1">{{ __('portal.trash') }}</h2>
            <div class="portal-soft small">{{ __('portal.trash_hint') }}</div>
        </div>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input name="search" class="form-control" style="min-width: 260px;" placeholder="{{ __('portal.search') }}" value="{{ $search ?? request('search') }}">
            <button class="btn btn-outline-secondary">{{ __('portal.find') }}</button>
        </form>
    </div>
</div>

<div class="row g-3">
    @foreach([
        ['title' => __('portal.sites'), 'type' => 'site', 'items' => $sites],
        ['title' => __('portal.companies'), 'type' => 'company', 'items' => $companies],
        ['title' => __('portal.ftp'), 'type' => 'ftp', 'items' => $ftpAccounts],
        ['title' => __('portal.hosting'), 'type' => 'hosting', 'items' => $hostings],
        ['title' => __('portal.hosting_accounts'), 'type' => 'hosting_account', 'items' => $hostingAccounts],
    ] as $group)
        <div class="col-12">
            <div class="portal-card p-4">
                <h3 class="h6 mb-3">{{ $group['title'] }}</h3>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('portal.name') }}</th>
                            <th>{{ __('portal.type') }}</th>
                            <th>{{ __('portal.short') }}</th>
                            <th>{{ __('portal.deleted_at') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($group['items'] as $item)
                            @php
                                $trashPayload = [
                                    'type' => $group['title'],
                                    'deleted_at' => (string) $item->deleted_at,
                                    'company' => $item->company?->name ?? null,
                                    'site' => $item->site?->name ?? null,
                                    'sites' => $item->sites?->pluck('name')->values()->all() ?? [],
                                    'url' => $item->url ?? null,
                                    'host' => $item->host ?? null,
                                    'login' => $item->login ?? null,
                                    'provider' => $item->provider ?? null,
                                    'panel_url' => $item->panel_url ?? null,
                                ];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $item->name ?? $item->title ?? $item->host }}</td>
                                <td>{{ $group['title'] }}</td>
                                <td class="text-break">
                                    @if($group['type'] === 'site')
                                        {{ $item->url }}
                                    @elseif($group['type'] === 'company')
                                        {{ $item->manager_name ?? $item->contact ?? __('portal.empty') }}
                                    @elseif($group['type'] === 'ftp')
                                        {{ $item->host }}:{{ $item->port }}
                                    @elseif($group['type'] === 'hosting')
                                        {{ $item->provider ?? __('portal.empty') }}
                                    @else
                                        {{ $item->hosting?->name ?? __('portal.empty') }}
                                    @endif
                                </td>
                                <td>{{ $item->deleted_at }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#trashModal" data-trash-title="{{ $item->name ?? $item->title ?? $item->host }}" data-trash-body="{{ e(json_encode($trashPayload, JSON_UNESCAPED_UNICODE)) }}">
                                        {{ __('portal.details') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="portal-soft">{{ __('portal.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="trashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('portal.details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('portal.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="fw-semibold mb-2" data-trash-modal-title></div>
                <pre class="mb-0 small" style="white-space: pre-wrap;" data-trash-modal-body></pre>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('trashModal');
        if (!modal) return;

        const title = modal.querySelector('[data-trash-modal-title]');
        const body = modal.querySelector('[data-trash-modal-body]');

        modal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;

            title.textContent = button.getAttribute('data-trash-title') || '';
            const payload = button.getAttribute('data-trash-body') || '{}';
            try {
                body.textContent = JSON.stringify(JSON.parse(payload), null, 2);
            } catch (error) {
                body.textContent = payload;
            }
        });
    })();
</script>
@endpush
@endsection
