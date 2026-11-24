@extends('rider.layout')

@section('title', 'Rider sign in')

@section('content')
    @php($recaptcha = \App\CentralLogics\Helpers::get_business_settings('recaptcha'))
    @php($recaptchaOn = isset($recaptcha) && $recaptcha['status'] == 1)

    <div class="card" style="max-width:460px;margin:32px auto 0;">
        @include('partials._portal-login-header', [
            'heading' => 'Rider sign in',
            'subtitle' => 'Use the phone number and password you registered with.',
        ])

        <form method="post" action="{{ route('rider.login.submit') }}" id="rider-login-form">
            @csrf

            <div class="field">
                <label for="phone">Phone number</label>
                <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                       autocomplete="username" inputmode="tel" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password"
                       autocomplete="current-password" required>
            </div>

            @if($recaptchaOn)
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
                <input type="hidden" name="set_default_captcha" id="set_default_captcha" value="0">

                <div class="field" id="image-captcha-block" style="display:none;">
                    <label for="custome_recaptcha">Enter the characters shown</label>
                    <div class="captcha-row">
                        <input type="text" name="custome_recaptcha" id="custome_recaptcha"
                               autocomplete="off" style="flex:1;">
                        <span class="captcha-img"><img src="<?php echo $custome_recaptcha->inline(); ?>" alt=""></span>
                    </div>
                    <p class="small muted" style="margin:6px 0 0;">
                        <a href="{{ route('rider.login') }}">Get a new code</a>
                    </p>
                </div>
            @else
                <div class="field">
                    <label for="custome_recaptcha">Enter the characters shown</label>
                    <div class="captcha-row">
                        <input type="text" name="custome_recaptcha" id="custome_recaptcha"
                               autocomplete="off" required style="flex:1;">
                        <span class="captcha-img"><img src="<?php echo $custome_recaptcha->inline(); ?>" alt=""></span>
                    </div>
                    <p class="small muted" style="margin:6px 0 0;">
                        <a href="{{ route('rider.login') }}">Get a new code</a>
                    </p>
                </div>
            @endif

            <button type="submit" style="width:100%;">Sign in</button>
        </form>

        <p class="small muted" style="margin-bottom:0;margin-top:14px;">
            Not registered yet? Riders sign up in the Aduanefie rider app.
            Contact your dispatcher if your application is still pending.
        </p>
    </div>

    @if($recaptchaOn)
        <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptcha['site_key'] }}"></script>
        <script>
            (function () {
                var form = document.getElementById('rider-login-form');
                var tokenField = document.getElementById('g-recaptcha-response');
                var fallbackField = document.getElementById('set_default_captcha');
                var imageBlock = document.getElementById('image-captcha-block');

                function useImageCaptcha() {
                    imageBlock.style.display = 'block';
                    fallbackField.value = '1';
                    document.getElementById('custome_recaptcha').required = true;
                }

                form.addEventListener('submit', function (event) {
                    if (fallbackField.value === '1') {
                        return;
                    }
                    event.preventDefault();
                    if (typeof grecaptcha === 'undefined') {
                        useImageCaptcha();
                        return;
                    }
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{ $recaptcha['site_key'] }}', {action: 'rider_login'})
                            .then(function (token) {
                                tokenField.value = token;
                                form.submit();
                            })
                            .catch(useImageCaptcha);
                    });
                });
            })();
        </script>
    @endif
@endsection
