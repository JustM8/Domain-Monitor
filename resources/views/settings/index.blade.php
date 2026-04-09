@extends('layouts.app')

@section('content')

    <div class="card shadow-sm">
        <div class="card-body">

            <h5 class="mb-4">Settings</h5>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="/settings">
                @csrf

                <div class="row g-3">

                    {{-- EMAIL --}}
                    <div class="col-md-6">
                        <label class="form-label">Notification Email</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               value="{{ $settings->notification_email }}"
                               placeholder="example@gmail.com">
                    </div>

                    {{-- GLOBAL INTERVAL --}}
                    <div class="col-md-6">
                        <label class="form-label">Global Check Interval (seconds)</label>
                        <input type="number"
                               name="interval"
                               class="form-control"
                               value="{{ $settings->check_interval }}"
                               min="10">
                    </div>

                </div>

                {{-- ACTIONS --}}
                <div class="mt-4 d-flex justify-content-between align-items-center">

                    <div class="text-muted small">
                        If domain interval is not set, global interval will be used
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Save Settings
                    </button>

                </div>

            </form>

        </div>
    </div>

@endsection
