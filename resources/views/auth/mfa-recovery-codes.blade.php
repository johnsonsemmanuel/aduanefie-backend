@extends('auth.mfa-layout')

@section('title', 'Your recovery codes')
@section('heading', 'Save your recovery codes')
@section('subheading')
    Two-factor authentication is now active. These codes are shown once and are not stored in
    readable form. Keep them somewhere safe: each one signs you in a single time if you lose
    your authenticator.
@endsection

@section('content')
    <div class="mfa-codes">
        @foreach ($codes as $code)
            <div>{{ $code }}</div>
        @endforeach
    </div>

    <p class="mfa-note">
        If you leave this page without saving them you will need a server administrator to
        reset two-factor authentication for your account.
    </p>

    <a class="mfa-btn" style="text-align:center; text-decoration:none;" href="{{ $mfaRoutes['dashboard'] ?? route('admin.dashboard') }}">
        I have saved these codes
    </a>
@endsection
