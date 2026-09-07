@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Підтвердження email</div>

                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            Нове посилання для підтвердження надіслано на вашу електронну пошту.
                        </div>
                    @endif

                    Перш ніж продовжити, перевірте пошту на наявність листа з посиланням для підтвердження.
                    Якщо лист не надійшов,
                    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">натисніть тут, щоб надіслати ще раз</button>.
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
