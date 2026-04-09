@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('content')

    <style>
        .status-cell {
            min-width: 260px;
        }
        .error-text {
            font-size: 12px;
            line-height: 1.2;
        }
    </style>

    <div class="row">
        <div class="col-md-4">
            {{-- CREATE --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5>Add Domain</h5>

                    <form method="POST" action="{{ route('domains.store') }}">
                        @csrf

                        <div class="mb-2">
                            <input class="form-control" name="domain" placeholder="example.com" required>
                        </div>

                        <div class="mb-2">
                            <input class="form-control" name="check_interval" type="number" value="60">
                        </div>

                        <div class="mb-2">
                            <input class="form-control" name="timeout" type="number" value="5">
                        </div>

                        <div class="mb-2">
                            <select class="form-select" name="method">
                                <option value="GET">GET</option>
                                <option value="HEAD">HEAD</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">

            {{-- LIST --}}
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Your Domains</h5>

                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Domain</th>
                            <th style="width: 280px;">Status</th>
                            <th>Method</th>
                            <th>Interval</th>
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($domains as $domain)
                            @php $last = $domain->lastCheck; @endphp

                            <tr>
                                {{-- DOMAIN --}}
                                <td>
                                    <a href="{{ route('checks.index', ['domain_id' => $domain->id]) }}">
                                        <b>{{ parse_url($domain->domain, PHP_URL_HOST) ?? $domain->domain }}</b>
                                    </a>
                                </td>

                                {{-- STATUS --}}
                                <td class="status-cell">

                                    @if($last)

                                        {{-- SUCCESS --}}
                                        @if($last->status_code >= 200 && $last->status_code < 300)
                                            <div class="d-flex flex-column">
                                        <span class="badge bg-success">
                                            {{ $last->status_code }}
                                            ({{ number_format($last->response_time, 2) }}s)
                                        </span>

                                                <span class="text-muted small mt-1">
                                            OK response
                                        </span>
                                            </div>

                                            {{-- REDIRECT --}}
                                        @elseif($last->status_code >= 300 && $last->status_code < 400)
                                            <div class="d-flex flex-column">
                                        <span class="badge bg-warning text-dark">
                                            {{ $last->status_code }}
                                            ({{ number_format($last->response_time, 2) }}s)
                                        </span>

                                                <span class="text-muted small mt-1">
                                            Redirect
                                        </span>
                                            </div>

                                            {{-- FAIL --}}
                                        @else
                                            <div class="d-flex flex-column">

                                        <span class="badge bg-danger"
                                              data-bs-toggle="tooltip"
                                              title="{{ $last->error }}">
                                            Fail
                                        </span>

                                                {{-- ERROR --}}
                                                @if($last->error)
                                                    <span class="text-danger error-text mt-1">
                                                ⚠ {{ Str::limit(preg_replace('/https?:\/\/\S+/', '', $last->error), 80) }}
                                            </span>
                                                @else
                                                    <span class="text-muted small mt-1">
                                                No response / timeout
                                            </span>
                                                @endif

                                            </div>
                                        @endif

                                    @else
                                        <span class="badge bg-secondary">No data</span>
                                    @endif

                                </td>

                                <td>{{ $domain->method }}</td>
                                <td>{{ $domain->check_interval }}s</td>

                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">

                                        {{-- CHECK --}}
                                        <form method="POST"
                                              action="{{ route('domains.check', $domain) }}"
                                              onsubmit="handleCheck(this)">
                                            @csrf
                                            <button class="btn btn-sm btn-success">
                                                <span class="btn-text">Check</span>
                                                <span class="spinner-border spinner-border-sm d-none"></span>
                                            </button>
                                        </form>

                                        {{-- EDIT --}}
                                        <a href="{{ route('domains.edit', $domain) }}"
                                           class="btn btn-sm btn-warning">
                                            Edit
                                        </a>

                                        {{-- DELETE --}}
                                        <form method="POST" action="{{ route('domains.destroy', $domain) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">Delete</button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>

                    </table>

                </div>
            </div>
        </div>


    </div>
@endsection
@push('scripts')
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    </script>
@endpush
