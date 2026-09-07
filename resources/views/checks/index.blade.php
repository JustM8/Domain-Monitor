@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="mb-3">Історія перевірок</h5>

        <form method="GET" class="mb-3 d-flex gap-2 flex-wrap">
            <input name="search" class="form-control w-auto" style="min-width: 260px;" placeholder="Пошук" value="{{ $search ?? request('search') }}">
            <select name="domain_id" class="form-select w-auto">
                <option value="">Усі домени</option>

                @foreach($domains as $domain)
                    <option value="{{ $domain->id }}" {{ request('domain_id') == $domain->id ? 'selected' : '' }}>
                        {{ parse_url($domain->domain, PHP_URL_HOST) ?? $domain->domain }}
                    </option>
                @endforeach
            </select>

            <button class="btn btn-primary">Фільтрувати</button>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>Домен</th>
                    <th>Статус</th>
                    <th>Відповідь</th>
                    <th>Метод</th>
                    <th>Інтервал</th>
                    <th>Дата</th>
                    <th style="width: 300px;">Помилка</th>
                </tr>
                </thead>
                <tbody>
                @foreach($checks as $check)
                    <tr>
                        <td>
                            <b>{{ parse_url($check->domain->domain, PHP_URL_HOST) ?? $check->domain->domain }}</b>
                        </td>
                        <td>
                            @if($check->status_code >= 200 && $check->status_code < 300)
                                <span class="badge bg-success">{{ $check->status_code }}</span>
                            @elseif($check->status_code >= 300 && $check->status_code < 400)
                                <span class="badge bg-warning text-dark">{{ $check->status_code }}</span>
                            @else
                                <span class="badge bg-danger">Помилка</span>
                            @endif
                        </td>
                        <td>{{ number_format($check->response_time, 2) }}s</td>
                        <td>
                            <span class="badge bg-secondary">{{ $check->domain->method }}</span>
                        </td>
                        <td>{{ $check->domain->check_interval }}s</td>
                        <td>{{ $check->checked_at }}</td>
                        <td>
                            @if($check->error)
                                <span class="text-danger small"
                                      data-bs-toggle="tooltip"
                                      title="{{ $check->error }}">
                                    ⚠ {{ \Illuminate\Support\Str::limit($check->error, 80) }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $checks->links() }}
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
