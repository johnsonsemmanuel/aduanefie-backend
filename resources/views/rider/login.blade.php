@extends('rider.layout')

@section('title', 'Rider sign in')

@section('content')
    @php($recaptcha = \App\CentralLogics\Helpers::get_business_settings('recaptcha'))
    @php($recaptchaOn = isset($recaptcha) && $recaptcha['status'] == 1)
    @php($storeLogo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first() ?? \App\Models\BusinessSetting::where(['key' => 'icon'])->first())
    @php($storeLogoUrl = \App\CentralLogics\Helpers::get_full_url('business', $storeLogo?->value ?? '', $storeLogo?->storage[0]?->value ?? 'public', 'favicon'))

    <style>
        .portal-login-split {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            max-width: 880px;
            margin: 28px auto 0;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
        }
        .portal-login-left {
            padding: 36px 34px;
        }
        .portal-login-right {
            background: var(--bg);
            border-left: 1px solid var(--line);
            padding: 36px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .portal-login-right img {
            max-width: 180px;
            max-height: 80px;
            object-fit: contain;
            margin-bottom: 20px;
        }
        .portal-step-dots {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
        }
        .portal-step-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--line);
            transition: all .25s ease;
        }
        .portal-step-dot.active {
            width: 22px;
            background: var(--brand);
        }
        @media (max-width: 760px) {
            .portal-login-split {
                grid-template-columns: 1fr;
            }
            .portal-login-left {
                padding: 26px 20px;
            }
            .portal-login-right {
                display: none;
            }
        }
    </style>

    <div class="portal-login-split">
        <div class="portal-login-left">
            @include('partials._portal-login-header', [
                'heading' => 'Rider sign in',
                'subtitle' => 'Use the phone number and password you registered with.',
            ])

                <div class="portal-step-dots" aria-hidden="true">
                    <span class="portal-step-dot active" id="rider-dot-1"></span>
                    <span class="portal-step-dot" id="rider-dot-2"></span>
                </div>

                <form method="post" action="{{ route('rider.login.submit') }}" id="rider-login-form">
                    @csrf

                    <div id="rider-step-1">
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

                        <button type="button" id="rider-next-btn" style="width:100%;">Continue</button>
                    </div>

                    <div id="rider-step-2" style="display:none;">
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

                        <div style="display:flex;gap:10px;">
                            <button type="button" class="btn secondary" id="rider-back-btn">Back</button>
                            <button type="submit" style="flex:1;">Sign in</button>
                        </div>
                    </div>
                </form>

                <p class="small muted" style="margin-bottom:0;margin-top:16px;">
                    Not registered yet? Riders sign up in the Aduanefie rider app.
                    Contact your dispatcher if your application is still pending.
                </p>
        </div>

        <div class="portal-login-right">
            <img src="{{ $storeLogoUrl }}" alt="Aduanefie" onerror="this.style.display='none'">
            <h2 style="margin-bottom:8px;text-transform:none;letter-spacing:0;color:var(--ink);">Deliver Fresh, Earn Fast</h2>
            <p class="small muted" style="max-width:280px;margin:0;">
                Manage assigned farm pickups, hub dispatches, and daily delivery settlements from your Rider Portal.
            </p>
        </div>
    </div>

    <script>
        (function () {
            var step1 = document.getElementById('rider-step-1');
            var step2 = document.getElementById('rider-step-2');
            var dot1 = document.getElementById('rider-dot-1');
            var dot2 = document.getElementById('rider-dot-2');
            var phoneEl = document.getElementById('phone');
            var passEl = document.getElementById('password');
            var nextBtn = document.getElementById('rider-next-btn');
            var backBtn = document.getElementById('rider-back-btn');
            var recaptchaOn = {{ $recaptchaOn ? 'true' : 'false' }};

            function goStep(step) {
                if (step === 2) {
                    if (!phoneEl.reportValidity() || !passEl.reportValidity()) return;
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    dot1.classList.remove('active');
                    dot2.classList.add('active');
                    if (recaptchaOn && typeof grecaptcha === 'undefined') {
                        document.getElementById('image-captcha-block').style.display = 'block';
                        document.getElementById('set_default_captcha').value = '1';
                        document.getElementById('custome_recaptcha').required = true;
                    }
                } else {
                    step2.style.display = 'none';
                    step1.style.display = 'block';
                    dot2.classList.remove('active');
                    dot1.classList.add('active');
                }
            }

            if (nextBtn) nextBtn.addEventListener('click', function () { goStep(2); });
            if (backBtn) backBtn.addEventListener('click', function () { goStep(1); });
        })();
    </script>

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
