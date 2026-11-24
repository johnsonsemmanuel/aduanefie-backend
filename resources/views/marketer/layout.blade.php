<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $site_direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Marketer Portal') · Aduanefie</title>
    @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'icon'])->first() ?? \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
    @php($store_logo_url = \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon'))
    <link rel="shortcut icon" href="{{ $store_logo_url }}">
    <link rel="icon" type="image/png" href="{{ $store_logo_url }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/vendor.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/vendor/icon-set/style.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/theme.minc619.css?v=1.0') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/style.css') }}">
    <style>
        :root {
            --ink: var(--primary-clr, #67513A);
            --muted: #677788;
            --line: #E7EAF3;
            --bg: #F8FAFD;
            --brand: var(--primary-clr, #67513A);
            --danger: #b3261e;
            --warn: #8a5a00;
        }
        body {
            margin: 0;
            background: var(--bg);
            color: #1E2022;
        }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 16px 48px; }
        header.topbar { background: var(--ink); color: #fff; padding: 12px 0; margin-bottom: 18px; }
        header.topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-bottom: 0; }
        .brand { font-weight: 700; font-size: 16px; }
        .brand small { display: block; font-weight: 400; opacity: .75; font-size: 12px; }
        .who { font-size: 13px; text-align: right; opacity: .9; }
        nav.tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
        nav.tabs a {
            text-decoration: none; padding: 8px 14px; border-radius: 6px;
            border: 1px solid var(--line); background: #fff; color: #1E2022; font-size: 13px; font-weight: 600;
            height: 38px; display: inline-flex; align-items: center;
        }
        nav.tabs a.active { background: var(--brand); border-color: var(--brand); color: #fff; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 18px; margin-bottom: 16px; box-shadow: 0 3px 6px rgba(140, 152, 164, 0.05); }
        h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        h2 { font-size: 16px; font-weight: 700; margin: 0 0 12px; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #F8FAFD; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 12px; }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 14px; }
        .stat .label { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); font-weight: 600; }
        .stat .value { font-size: 20px; font-weight: 700; margin-top: 4px; color: #1E2022; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 12px; border: 1px solid var(--line); background: #f8fafc; font-weight: 600; }
        .badge.pending { color: var(--warn); border-color: #f0d9a8; background: #fdf6e6; }
        .badge.eligible { color: var(--brand); border-color: #b9e0d1; background: #eaf6f1; }
        .badge.in_payout { color: var(--primary-clr, #67513A); border-color: #c7d7fb; background: #eef3ff; }
        .badge.paid { color: #166534; border-color: #c7e6cf; background: #eefaf1; }
        .badge.void, .badge.rejected { color: var(--danger); border-color: #f2c9c6; background: #fdf1f0; }
        .badge.requested, .badge.approved { color: var(--warn); border-color: #f0d9a8; background: #fdf6e6; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=tel], input[type=password], input[type=email], select, textarea {
            width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 6px;
            font-size: 14px; background: #fff; color: #1E2022; font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { outline: 2px solid rgba(103, 81, 58, .2); border-color: var(--brand); }
        .field { margin-bottom: 14px; }
        button, .btn {
            display: inline-flex; align-items: center; justify-content: center;
            height: 38px; padding: 0 16px; border-radius: 6px; border: 1px solid var(--brand);
            background: var(--brand); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .btn.secondary, .btn--secondary { background: #fff; color: #1E2022; border-color: var(--line); }
        .btn-sm { height: 32px; padding: 0 12px; font-size: 12px; }
        .alert { border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; }
        .alert.error { background: #fdf1f0; border: 1px solid #f2c9c6; color: #7f1d1d; }
        .alert.ok { background: #eefaf1; border: 1px solid #c7e6cf; color: #14532d; }
        .captcha-row { display: flex; gap: 10px; align-items: center; }
        .captcha-row .captcha-img { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; line-height: 0; background: #fff; }
        .captcha-row img { height: 44px; width: auto; display: block; }
        .empty { color: var(--muted); font-size: 13px; padding: 8px 0; margin: 0; }
        .pager { display: flex; gap: 10px; margin-top: 14px; font-size: 13px; }
        .mobile-bottom-nav { display: none; }
        @media (max-width: 720px) {
            .wrap { padding-bottom: 84px; }
            .mobile-bottom-nav {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 90;
                background: #fff;
                border-top: 1px solid var(--line);
                box-shadow: 0 -4px 14px rgba(0,0,0,0.06);
                padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
            }
            .mobile-bottom-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 2px;
                text-decoration: none;
                color: #1E2022;
                font-size: 11px;
                font-weight: 600;
                padding: 4px 2px;
                border-radius: 6px;
                opacity: 0.75;
            }
            .mobile-bottom-nav a.active {
                color: var(--brand);
                background: rgba(103, 81, 58, 0.08);
                opacity: 1;
            }
            .mobile-bottom-nav .nav-ico { font-size: 15px; line-height: 1; }
        }
    </style>
</head>
<body>
@hasSection('topbar')
    <header class="topbar">
        <div class="wrap">
            <div class="brand">Aduanefie<small>Marketer Portal</small></div>
            <div class="who">@yield('topbar')</div>
        </div>
    </header>
@endif

<div class="wrap">
    @hasSection('nav')
        <nav class="tabs">@yield('nav')</nav>
    @endif

    @if ($errors->any())
        <div class="alert error">
            <strong>Please check the following:</strong>
            <ul style="margin:6px 0 0 18px;padding:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif

    @yield('content')
</div>

@hasSection('nav')
    <nav class="mobile-bottom-nav" aria-label="Marketer mobile navigation">
        <a href="{{ route('marketer.dashboard') }}" class="{{ request()->routeIs('marketer.dashboard') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true"><i class="tio-home-vs-1-outlined"></i></span>
            <span>Home</span>
        </a>
        <a href="{{ route('marketer.register-farmer') }}" class="{{ request()->routeIs('marketer.register-farmer*') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true"><i class="tio-add-circle-outlined"></i></span>
            <span>Register</span>
        </a>
        <a href="{{ route('marketer.regional-tours') }}" class="{{ request()->routeIs('marketer.regional-tours*') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true"><i class="tio-poi-outlined"></i></span>
            <span>Tours</span>
        </a>
        <a href="{{ route('marketer.backyard-farms') }}" class="{{ request()->routeIs('marketer.backyard-farms*') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true"><i class="tio-shop-outlined"></i></span>
            <span>Farms</span>
        </a>
        <a href="{{ route('marketer.payouts') }}" class="{{ request()->routeIs('marketer.payout*') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true"><i class="tio-wallet-outlined"></i></span>
            <span>Payouts</span>
        </a>
    </nav>
@endif
</body>
</html>
