@extends('marketer.layout')

@section('title', 'Reset Marketer Access')

@section('content')
    <div class="card" style="max-width:460px;margin:32px auto 0;">
        <h1>Get a one-click sign-in link</h1>
        <p class="muted small" style="margin-top:0;">
            Enter your registered email address or phone number. We will send a sign-in link via SMS and email so you can log right back in.
        </p>

        <form method="post" action="{{ route('marketer.forgot.send') }}">
            @csrf

            <div class="field">
                <label for="identifier">Email or Phone Number</label>
                <input type="text" name="identifier" id="identifier" value="{{ old('identifier') }}"
                       placeholder="e.g. name@gmail.com or 024XXXXXXX" required autofocus>
            </div>

            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit">Send Sign-In Link</button>
                <a href="{{ route('marketer.login') }}" class="btn secondary">Back to sign in</a>
            </div>
        </form>
    </div>
@endsection
