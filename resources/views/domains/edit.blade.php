@extends('layouts.app')

@section('content')

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">

                    <h5>Edit Domain</h5>

                    <form method="POST" action="{{ route('domains.update', $domain) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-2">
                            <input class="form-control" name="domain" value="{{ $domain->domain }}" required>
                        </div>

                        <div class="mb-2">
                            <input class="form-control" name="check_interval" type="number" value="{{ $domain->check_interval }}">
                        </div>

                        <div class="mb-2">
                            <input class="form-control" name="timeout" type="number" value="{{ $domain->timeout }}">
                        </div>

                        <div class="mb-2">
                            <select class="form-select" name="method">
                                <option value="GET" {{ $domain->method === 'GET' ? 'selected' : '' }}>GET</option>
                                <option value="HEAD" {{ $domain->method === 'HEAD' ? 'selected' : '' }}>HEAD</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100">Update</button>

                    </form>

                </div>
            </div>

        </div>


    </div>

@endsection
