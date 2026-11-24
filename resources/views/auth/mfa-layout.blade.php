<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', 'Two-factor verification')</title>

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
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/custom.css') }}">

    <style>
        .mfa-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        .mfa-input { width: 100%; height: calc(1.6em + 1.625rem + 2px); padding: .75rem 1rem; font-size: 1rem; line-height: 1.6; color: #1e2022; background-color: #fff; border: .0625rem solid #e7eaf3; border-radius: .3125rem; }
        .mfa-btn { display: block; width: 100%; margin-top: 18px; padding: .75rem 1rem; font-size: 1rem; font-weight: 600; border: 0; border-radius: .3125rem; background: var(--primary-clr, #67513A); color: #fff; cursor: pointer; }
        .mfa-note { color: #677788; font-size: 13px; margin-top: 16px; }
        .mfa-secret { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 18px; letter-spacing: .12em; background: #f8fafd; border: 1px dashed #e7eaf3; border-radius: 8px; padding: 14px; text-align: center; word-break: break-all; }
        .mfa-link { display: inline-block; margin-top: 10px; font-size: 13px; }
        .mfa-codes { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 16px; line-height: 2; background: #f8fafd; border-radius: 8px; padding: 14px 18px; }
        .mfa-back { display: inline-block; margin-top: 18px; font-size: 13px; }
        .alert ul { margin-bottom: 0; padding-left: 18px; }
    </style>
</head>
<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <img class="auth-left-logo" src="{{ $brand_logo_url }}" alt="Aduanefie"
                 onerror="this.src='{{ asset('/public/assets/admin/img/favicon.png') }}'">
            <div class="auth-left-cont">
                <h2 class="title">...building <span class="d-block">Africa's biggest</span> <strong class="aduanefie-auth-accent">marketplace.</strong></h2>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <div class="auth-wrapper-form">
                <div class="auth-header">
                    <div class="mb-4">
                        <h2 class="title">@yield('heading', 'Two-factor verification')</h2>
                        <div>@yield('subheading')</div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</main>
</body>
</html>
