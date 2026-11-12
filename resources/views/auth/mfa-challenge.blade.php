@extends('auth.mfa-layout')

@section('title', 'Two-factor verification')
@section('heading', 'Two-factor verification')
@section('subheading')
    Enter the 6-digit code from your authenticator app{{ !empty($email) ? ' for ' . $email : '' }}.
    You can also use one of your one-time recovery codes.
@endsection

@section('content')
    <form method="post" action="{{ $mfaRoutes['verify'] ?? route('admin.mfa.verify') }}" autocomplete="off">
        @csrf
        <label class="mfa-label" for="code">Authentication code</label>
        <input class="mfa-input" id="code" name="code" type="text" inputmode="numeric"
               autocomplete="one-time-code" autofocus required maxlength="32" placeholder="123456"
               value="">

        <button class="mfa-btn" type="submit">Verify and continue</button>
    </form>

    <p class="mfa-note">
        Lost access to your authenticator? Enter a recovery code in the same field.
        Each recovery code works once.
    </p>

    <a class="mfa-back" href="{{ $mfaRoutes['login'] ?? route('admin.login') }}">Back to sign in</a>
@endsection
