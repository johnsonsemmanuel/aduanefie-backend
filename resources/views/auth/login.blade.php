<!DOCTYPE html>
<?php
    $log_email_succ = session()->get('log_email_succ');
    $isAdmin = ($role ?? '') === 'admin';
    $isVendor = in_array($role ?? '', ['vendor', 'vendor_employee']);
    $isMarketer = ($role ?? '') === 'marketer';
    $forgotRoute = $forgotRoute ?? route('reset-password');

    $captcha = $captcha ?? null;
    if (! $captcha) {
        $captcha = new \Gregwar\Captcha\CaptchaBuilder();
        $captcha->build();
        session()->put('six_captcha', $captcha->getPhrase());
    }
    $showCaptcha = true;
    $recaptcha = $recaptcha ?? \App\CentralLogics\Helpers::get_business_settings('recaptcha');
    $recaptchaOn = ! empty($recaptcha['status']) && (int) $recaptcha['status'] === 1
        && ! empty($recaptcha['site_key']);
?>
<html dir="{{ $site_direction }}" lang="{{ $locale }}" class="{{ $site_direction === 'rtl' ? 'active' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate('messages.login') }}</title>

    @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'icon'])->first() ?? \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
    @php($store_logo_url = \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon'))
    @php($brand_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first() ?? $store_logo)
    @php($brand_logo_url = \App\CentralLogics\Helpers::get_full_url('business', $brand_logo?->value ?? '', $brand_logo?->storage[0]?->value ?? 'public', 'favicon'))
    <link rel="shortcut icon" href="{{ $store_logo_url }}">
    <link rel="icon" type="image/png" href="{{ $store_logo_url }}">

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/assets/admin') }}/css/vendor.min.css">
    <link rel="stylesheet" href="{{ asset('public/assets/admin') }}/vendor/icon-set/style.css">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/theme.minc619.css?v=1.0') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/style.css?v=2.3') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin') }}/css/toastr.css">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/custom.css') }}">
</head>

<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <img class="auth-left-logo onerror-image" data-onerror-image="{{ asset('/public/assets/admin/img/favicon.png') }}"
                 src="{{ $brand_logo_url }}" alt="Aduanefie">
            <div class="auth-left-cont">
                <h2 class="title">...building <span class="d-block">Africa's biggest</span> <strong class="aduanefie-auth-accent">marketplace.</strong></h2>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="auth-header">
                    <div class="mb-5">
                        <h2 class="title">
                            {{ $isAdmin ? translate('messages.signin') : ($isMarketer ? translate('Marketer Sign In') : translate('messages.store_signin')) }}
                        </h2>
                        <div>
                            {{ $isAdmin ? translate('messages.welcome_back_login_to_your_panel') : ($isMarketer ? translate('Sign in to your marketer panel') : translate('messages.signin_to_your_store_panel')) }}.
                        </div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ $postRoute ?? ($isAdmin ? route('admin.login.submit') : route('login_post')) }}" method="post" id="loginForm" novalidate>
                    @csrf
                    <input type="hidden" name="role" value="{{ $role ?? 'admin' }}">

                    <div class="js-form-message form-group">
                        <label class="input-label text-capitalize" for="signinSrEmail">{{ translate('messages.your_email') }}</label>
                        <input type="email" class="form-control form-control-lg" name="email" id="signinSrEmail"
                               tabindex="1" placeholder="email@address.com" value="{{ $email ?? old('email') }}"
                               aria-label="email@address.com" required
                               data-msg="{{ translate('Please_enter_a_valid_email_address.') }}">
                    </div>

                    <div class="js-form-message form-group mb-2">
                        <label class="input-label" for="signupSrPassword" tabindex="0">
                            <span class="d-flex justify-content-between align-items-center">
                                {{ translate('messages.password') }}
                            </span>
                        </label>
                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                   name="password" id="signupSrPassword"
                                   placeholder="{{ translate('messages.password_length_placeholder', ['length' => '6+']) }}"
                                   value="{{ $password ?? '' }}"
                                   aria-label="{{ translate('messages.password_length_placeholder', ['length' => '6+']) }}"
                                   required
                                   data-msg="{{ translate('messages.invalid_password_warning') }}"
                                   data-hs-toggle-password-options='{
                                       "target": "#changePassTarget",
                                       "defaultClass": "tio-hidden-outlined",
                                       "showClass": "tio-visible-outlined",
                                       "classChangeTarget": "#changePassIcon"
                                   }'>
                            <div id="changePassTarget" class="input-group-append">
                                <a class="input-group-text" href="javascript:">
                                    <i id="changePassIcon" class="tio-visible-outlined"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    @if($showCaptcha)
                        @if($recaptchaOn)
                            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
                            <input type="hidden" name="set_default_captcha" id="set_default_captcha" value="0">

                            <div class="form-group mt-3" id="image-captcha-block" style="display:none;">
                                <label class="input-label">{{ translate('Enter recaptcha value') }}</label>
                                <input type="text" name="custome_recaptcha" id="custome_recaptcha"
                                       class="form-control form-control-lg" autocomplete="off">
                                <img src="{{ $captcha->inline() }}" class="rounded captcha-img mt-2" alt="captcha">
                            </div>
                        @else
                            <div class="mt-3">
                                @include('auth.custom-captcha', ['custome_recaptcha' => $captcha])
                            </div>
                        @endif
                    @endif

                    <div class="d-flex justify-content-between mt-4">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="termsCheckbox"
                                       {{ $password ? 'checked' : '' }} name="remember">
                                <label class="custom-control-label text-muted" for="termsCheckbox">
                                    {{ translate('messages.remember_me') }}
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="forget-password">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" class="text-primary" data-target="#forgetPassModal">
                                    {{ $isAdmin ? translate('Forget Password') : translate('messages.Forget Password') }}?
                                </span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-xxl-3">
                        {{ translate('messages.login') }}
                    </button>
                </form>

                @if($isVendor)
                <div class="mt-4 text-center">
                    @if($role === 'vendor')
                        <a href="{{ route('store.employee.login') }}" class="text-muted small">{{ translate('Store employee login') }}</a>
                    @else
                        <a href="{{ route('store.login') }}" class="text-muted small">{{ translate('Store owner login') }}</a>
                    @endif
                </div>
                @endif

                @if(env('APP_MODE') == 'demo')
                <div class="auto-fill-data-copy">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            @if($isAdmin)
                                <span class="d-block"><strong>Email</strong> : admin@admin.com</span>
                                <span class="d-block"><strong>Password</strong> : 12345678</span>
                            @else
                                <span class="d-block"><strong>Email</strong> : test.restaurant@gmail.com</span>
                                <span class="d-block"><strong>Password</strong> : 12345678</span>
                            @endif
                        </div>
                        <div>
                            <button class="btn action-btn btn--primary m-0 copy_cred"><i class="tio-copy"></i></button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="forgetPassModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header justify-content-end">
        <span type="button" class="close-modal-icon" data-dismiss="modal">
            <i class="tio-clear"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="forget-pass-content">
            <img src="{{ asset('/public/assets/admin/img/send-mail.svg') }}" alt="">
            <h4>{{ translate('Send_Mail_to_Your_Email') }} ?</h4>
            <p>{{ translate('A mail will be send to your registered email with a link to change password') }}</p>
            <a class="btn btn-lg btn-block btn--primary mt-3" href="{{ $forgotRoute }}">
                {{ translate('Send Mail') }}
            </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="{{ asset('public/assets/admin') }}/js/vendor.min.js"></script>
<script src="{{ asset('public/assets/admin') }}/js/theme.min.js"></script>
<script src="{{ asset('public/assets/admin') }}/js/toastr.js"></script>
{!! Toastr::message() !!}

@if ($errors->any())
<script>
    "use strict";
    @foreach($errors->all() as $error)
    toastr.error('{{ translate($error) }}', '{{ translate("Error") }}', {
        CloseButton: true,
        ProgressBar: true
    });
    @endforeach
</script>
@endif

@if ($log_email_succ)
@php(session()->forget('log_email_succ'))
<script>
    "use strict";
    if (window.jQuery && typeof $('#successMailModal').modal === 'function') {
        $('#successMailModal').modal('show');
    }
</script>
@endif

<script>
    "use strict";
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof HSTogglePassword !== 'undefined') {
            document.querySelectorAll('.js-toggle-password').forEach(function (el) {
                new HSTogglePassword(el).init();
            });
        }
        document.querySelectorAll('.onerror-image').forEach(function (imgEl) {
            imgEl.addEventListener('error', function () {
                var fallback = imgEl.getAttribute('data-onerror-image');
                if (fallback && imgEl.getAttribute('src') !== fallback) {
                    imgEl.setAttribute('src', fallback);
                }
            });
        });
    });
</script>

@if($showCaptcha && $recaptchaOn)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptcha['site_key'] }}"></script>
    <script>
        (function () {
            var form = document.getElementById('loginForm');
            var tokenField = document.getElementById('g-recaptcha-response');
            var fallback = document.getElementById('set_default_captcha');
            var imageBlock = document.getElementById('image-captcha-block');

            function useImageCaptcha() {
                imageBlock.style.display = 'block';
                fallback.value = '1';
                document.getElementById('custome_recaptcha').required = true;
            }

            form.addEventListener('submit', function (event) {
                if (fallback.value === '1') {
                    return;
                }
                event.preventDefault();
                if (typeof grecaptcha === 'undefined') {
                    useImageCaptcha();
                    return;
                }
                grecaptcha.ready(function () {
                    grecaptcha.execute('{{ $recaptcha['site_key'] }}', {action: '{{ $recaptcha_action ?? 'login' }}'})
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
</body>
</html>
