<!DOCTYPE html>
    <?php

        $log_email_succ = session()->get('log_email_succ');
        $mfaRequired = (bool) ($mfa_required ?? false);
        $mfaMode = $mfa_mode ?? null;
        $mfaEmail = old('email');
        $isAdmin = ($role ?? '') === 'admin';
        $isVendor = in_array($role ?? '', ['vendor', 'vendor_employee']);
    ?>

<html dir="{{ $site_direction }}" lang="{{ $locale }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <!-- Required Meta Tags Always Come First -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Title -->
    <title>{{translate('messages.login')}}</title>

    <!-- Favicon -->
    @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first() ?? \App\Models\BusinessSetting::where(['key' => 'icon'])->first())
    @php($store_logo_url = \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value?? '', $store_logo?->storage[0]?->value ?? 'public','favicon'))
    <link rel="shortcut icon" href="{{ $store_logo_url }}">
    <link rel="icon" type="image/png" href="{{ $store_logo_url }}">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- CSS Implementing Plugins -->
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <!-- CSS Front Template -->
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css?v=2.2')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/custom.css')}}">

    <style>
        /* Admin login theme colors from shared color component */
        :root {
            --primary-clr: #67513A;
            --secondary-clr: #8B9F69;
            --accent-clr: #5E7042;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-clr) 0%, #5E7042 100%);
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('{{asset('/public/assets/admin/img/favicon.png')}}') repeat;
            opacity: 0.03;
            pointer-events: none;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 40px;
            color: #fff;
        }

        .login-brand img {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .login-brand h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #fff;
        }

        .login-brand h2 span {
            display: block;
            font-size: 14px;
            font-weight: 400;
            opacity: 0.85;
            margin-top: 4px;
        }

        .login-brand .accent {
            color: var(--secondary-clr);
        }

        /* Sliding panel container */
        .slide-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .slide-track {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 200%;
            position: relative;
        }

        .slide-container.mfa-active .slide-track {
            transform: translateX(-50%);
        }

        .slide-step {
            flex: 0 0 50%;
            width: 50%;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        .slide-step-header {
            margin-bottom: 32px;
        }

        .slide-step-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-clr);
            margin-bottom: 8px;
        }

        .slide-step-subtitle {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #E0D6BD;
            margin: 0 4px;
            transition: all 0.3s ease;
        }

        .slide-container.mfa-active .step-dot:nth-child(1) {
            background: var(--primary-clr);
        }
        .slide-container.mfa-active .step-dot:nth-child(2) {
            background: var(--secondary-clr);
        }

        .slide-footer {
            margin-top: auto;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        .slide-footer a {
            color: var(--primary-clr);
            font-weight: 600;
            text-decoration: none;
        }

        .slide-footer a:hover {
            text-decoration: underline;
        }

        .mfa-info {
            background: #F0F7EE;
            border: 1px solid var(--secondary-clr);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .mfa-info h4 {
            margin: 0 0 8px 0;
            color: var(--primary-clr);
            font-size: 15px;
        }

        .mfa-info p {
            margin: 0;
            color: #67513A;
            font-size: 13px;
            opacity: 0.8;
        }

        .color-quick-pick {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .color-quick-pick label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .color-row {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .color-chip {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
        }

        .color-chip:hover {
            transform: scale(1.1);
            border-color: rgba(0,0,0,0.2);
        }

        .color-chip.active {
            border-color: #222;
            transform: scale(1.15);
            box-shadow: 0 0 0 2px rgba(255,255,255,0.8);
        }

        /* Error styling */
        .has-error .form-control {
            border-color: #dc3545;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 12px;
            margin-top: 4px;
        }

        .btn-loading {
            position: relative;
            color: transparent !important;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        @media (max-width: 576px) {
            .login-wrapper { padding: 20px 16px; }
            .slide-step { padding: 24px 20px; }
        }
    </style>
</head>

<body>
<!-- ========== MAIN CONTENT ========== -->
<main id="content" role="main" class="main login-wrapper">
    <div class="login-brand">
        <img class="onerror-image"
             data-onerror-image="{{asset('/public/assets/admin/img/favicon.png')}}"
             src="{{ $store_logo_url }}"
             alt="Aduanefie">
        <h2>...building <span class="d-block">Africa's biggest</span> <strong class="accent">marketplace.</strong></h2>
    </div>

    <div class="slide-container {{ $mfaRequired ? 'mfa-active' : '' }}">
        <div class="step-indicator">
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div class="slide-track">
            <!-- Step 1: Credentials -->
            <div class="slide-step">
                <div class="slide-step-header">
                    <h2 class="slide-step-title">{{ $isAdmin ? translate('messages.welcome_back') : translate('messages.welcome_back_store') }}</h2>
                    <div class="slide-step-subtitle">
                        {{ $isAdmin ? translate('messages.signin_to_your_admin_panel') : translate('messages.signin_to_your_store_panel') }}.
                    </div>
                </div>

                <form class="" action="{{ $postRoute ?? ($isAdmin ? route('admin.login.submit') : route('login_post')) }}" method="post" id="loginForm" novalidate>
                    @csrf
                    <input type="hidden" name="role" value="{{ $role ?? 'admin' }}">

                    <x-ui.form-input
                        name="email"
                        label="{{ translate('messages.your_email') }}"
                        type="email"
                        placeholder="email@address.com"
                        value="{{ old('email') }}"
                        required
                        icon="tio-mail"
                        dataMsg="{{ translate('Please_enter_a_valid_email_address.') }}"
                    />

                    <x-ui.form-input
                        name="password"
                        label="{{ translate('messages.password') }}"
                        type="password"
                        placeholder="{{ translate('messages.password_length_placeholder',['length'=>'6+']) }}"
                        value="{{ old('password') }}"
                        required
                        togglePassword
                        icon="tio-lock"
                        dataMsg="{{ translate('messages.invalid_password_warning') }}"
                    />

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="rememberCheckbox"
                                       name="remember">
                                <label class="custom-control-label text-muted" for="rememberCheckbox">
                                    {{translate('messages.remember_me')}}
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

                    <div class="mt-5">
                        <x-ui.form-button
                            label="{{translate('messages.login') }}"
                            class="primary"
                            size="lg"
                            block
                        />
                    </div>
                </form>

                @if(env('APP_MODE') == 'demo')
                <div class="auto-fill-data-copy mt-4">
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

                <div class="color-quick-pick mt-5">
                    <label>Theme Color</label>
                    <div class="color-row">
                        <a class="color-chip" style="background-color: #67513A;" onclick="setLoginColor('--primary-clr', '#67513A', this)" data-var="--primary-clr"></a>
                        <a class="color-chip" style="background-color: #8B9F69;" onclick="setLoginColor('--secondary-clr', '#8B9F69', this)" data-var="--secondary-clr"></a>
                        <a class="color-chip" style="background-color: #5E7042;" onclick="setLoginColor('--accent-clr', '#5E7042', this)" data-var="--accent-clr"></a>
                        <a class="color-chip" style="background-color: #1A5F7A;" onclick="setLoginColor('--primary-clr', '#1A5F7A', this)" data-var="--primary-clr"></a>
                        <a class="color-chip" style="background-color: #C0392B;" onclick="setLoginColor('--primary-clr', '#C0392B', this)" data-var="--primary-clr"></a>
                    </div>
                </div>
            </div>

            <!-- Step 2: Two-Factor Authentication (slides in on same page) -->
            <div class="slide-step">
                <div class="slide-step-header">
                    <h2 class="slide-step-title">{{ translate('messages.two_factor_verification') }}</h2>
                    <div class="slide-step-subtitle">
                        {{ $mfaMode === 'setup'
                            ? translate('messages.setup_your_authenticator')
                            : translate('messages.enter_code_from_authenticator', ['email' => $mfaEmail ?? '']) }}
                    </div>
                </div>

                <div class="mfa-info">
                    <h4>{{ translate('messages.secure_signin') }}</h4>
                    <p>{{ translate('messages.enter_6_digit_code') }}</p>
                </div>

                <form method="post" action="{{ route('admin.mfa.verify') }}" id="mfaForm" autocomplete="off">
                    @csrf

                    <x-ui.form-input
                        name="code"
                        label="{{ translate('messages.authentication_code') }}"
                        type="text"
                        placeholder="123456"
                        required
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                        autofocus
                        icon="tio-shield"
                    />

                    <div class="mt-4">
                        <x-ui.form-button
                            label="{{ translate('messages.verify_and_continue') }}"
                            class="primary"
                            size="lg"
                            block
                        />
                    </div>

                    <input type="hidden" name="remember" value="{{ old('remember', false) ? '1' : '0' }}">
                </form>

                <div class="slide-footer mt-4">
                    <p>
                        {{ translate('messages.lost_access_authenticator') }} <br>
                        <a href="{{ route('admin.login') }}">{{ translate('messages.back_to_sign_in') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
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
                <img src="{{asset('/public/assets/admin/img/send-mail.svg')}}" alt="">
                <h4>{{ translate('Send_Mail_to_Your_Email') }} ?</h4>
                <p>{{ translate('A mail will be send to your registered email with a link to change password') }}</p>
                <a class="btn btn-lg btn-block btn--primary mt-3" href="{{route('reset-password')}}">
                    {{ translate('Send Mail') }}
                </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- JS Implementing Plugins -->
    <script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
    {!! Toastr::message() !!}

    @if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{translate($error)}}', '{{ translate("Error") }}', {
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
        $('#successMailModal').modal('show');
    </script>
    @endif

    <script>
        "use strict";

        // Initialize show/hide password
        $(document).on('ready', function () {
            $('.js-toggle-password').each(function () {
                new HSTogglePassword(this).init()
            });

            // Auto-focus the code input when MFA panel is active
            {{ $mfaRequired ? '$("#mfaForm input[name=code]").trigger("focus");' : '' }}
        });

        // Theme color quick pick
        function setLoginColor(varName, value, el) {
            document.documentElement.style.setProperty(varName, value);
            document.querySelectorAll('.color-chip').forEach(function (e) { e.classList.remove('active'); });
            el.classList.add('active');
        }

        $(document).ready(function() {
            $('.onerror-image').on('error', function() {
                let img = $(this).data('onerror-image')
                $(this).attr('src', img);
            });
        });
    </script>

    @if(env('APP_MODE')=='demo')
    <script>
        "use strict";
        $('.copy_cred').on('click', function () {
            @if($isAdmin)
            $('#loginForm input[name=email]').val('admin@admin.com');
            $('#loginForm input[name=password]').val('12345678');
            @else
            $('#loginForm input[name=email]').val('test.restaurant@gmail.com');
            $('#loginForm input[name=password]').val('12345678');
            @endif
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
    </script>
    @endif

    <!-- IE Support -->
    <script>
        if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{asset('public//assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
    </script>
</body>
</html>
