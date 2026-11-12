@extends('auth.mfa-layout')

@section('title', 'Set up two-factor authentication')
@section('heading', 'Set up two-factor authentication')
@section('subheading')
    Your account has not been enrolled yet. Add the key below to your authenticator app, then
    enter the current 6-digit code to finish signing in. You cannot reach the
    {{ $panelLabel ?? 'admin panel' }} until this is done.
@endsection

@section('content')
    <p class="mfa-label">Account</p>
    <p style="margin-bottom:16px;">{{ $email }}</p>

    <p class="mfa-label">Secret key (manual entry)</p>
    <div class="mfa-secret">{{ $secret }}</div>

    <a class="mfa-link" href="{{ $provisioningUri }}">
        Open in your authenticator app
    </a>

    <p class="mfa-note">
        In Google Authenticator, Authy or 1Password choose "Enter a setup key" and paste the
        key above. Time-based codes require the clock on your device to be correct.
    </p>

    <form method="post" action="{{ $mfaRoutes['setup_submit'] ?? route('admin.mfa.setup.submit') }}" autocomplete="off" style="margin-top:22px;">
        @csrf
        <label class="mfa-label" for="code">Code from your authenticator app</label>
        <input class="mfa-input" id="code" name="code" type="text" inputmode="numeric"
               autocomplete="one-time-code" autofocus required maxlength="32" placeholder="123456"
               value="">

        <button class="mfa-btn" type="submit">Confirm and continue</button>
    </form>

    <a class="mfa-back" href="{{ $mfaRoutes['login'] ?? route('admin.login') }}">Back to sign in</a>
@endsection
