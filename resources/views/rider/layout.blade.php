<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $site_direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Rider Portal') · Aduanefie</title>
    <style>
        :root {
            --ink: #67513A;
            --muted: #8B9F69;
            --line: #E0D6BD;
            --bg: #FAF8F5;
            --brand: #67513A;
            --brand-ink: #ffffff;
            --danger: #b3261e;
            --warn: #8a5a00;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        a { color: var(--brand); }
        .wrap { max-width: 780px; margin: 0 auto; padding: 0 16px 48px; }
        header.topbar {
            background: var(--ink);
            color: #fff;
            padding: 14px 0;
            margin-bottom: 20px;
        }
        header.topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-bottom: 0; }
        .brand { font-weight: 700; letter-spacing: .2px; }
        .brand small { display: block; font-weight: 400; opacity: .7; font-size: 12px; }
        .who { font-size: 13px; text-align: right; opacity: .85; }
        nav.tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        nav.tabs a {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: 14px;
        }
        nav.tabs a.active { background: var(--brand); border-color: var(--brand); color: var(--brand-ink); }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 24px 0 10px; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--line); font-size: 14px; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        tr:last-child td { border-bottom: 0; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; background: #f4efe6; color: var(--muted); border: 1px solid var(--line); }
        .pill.live { background: #eef3e6; color: #536636; border-color: #d6e2c3; }
        .pill.done { background: #f4efe6; color: var(--muted); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
        .stat { border: 1px solid var(--line); border-radius: 12px; padding: 14px; background: #fff; }
        .stat .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
        .stat .value { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; }
        .alert.error { background: #fdecea; color: var(--danger); border: 1px solid #f6c9c5; }
        .alert.info { background: #fff8e6; color: var(--warn); border: 1px solid #f0dfae; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=tel], input[type=password], input[type=email] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
            color: var(--ink);
        }
        input:focus { outline: 2px solid rgba(103, 81, 58, .25); border-color: var(--brand); }
        .field { margin-bottom: 14px; }
        button, .btn {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 10px;
            border: 1px solid var(--brand);
            background: var(--brand);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        button.link { background: none; border: 0; color: #fff; padding: 0; font-size: 13px; text-decoration: underline; }
        .captcha-row { display: flex; gap: 10px; align-items: center; }
        .captcha-row .captcha-img { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; line-height: 0; background: #fff; }
        .captcha-row img { height: 48px; width: auto; display: block; }
        .captcha-row button.reload { background: #fff; color: var(--ink); border: 1px solid var(--line); padding: 8px 12px; font-size: 13px; }
        .empty { color: var(--muted); font-size: 14px; padding: 8px 0; }
        .mobile-bottom-nav { display: none; }
        @media (max-width: 767.98px) {
            body { padding-bottom: 74px; }
            nav.tabs { display: none; }
            .mobile-bottom-nav {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 40;
                background: #ffffff;
                border-top: 1px solid var(--line);
                box-shadow: 0 -4px 16px rgba(103, 81, 58, 0.08);
                padding: 6px 8px calc(6px + env(safe-area-inset-bottom, 0px));
                gap: 4px;
            }
            .mobile-bottom-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 2px;
                text-decoration: none;
                color: var(--ink);
                opacity: 0.72;
                font-size: 11px;
                font-weight: 600;
                padding: 6px 4px;
                border-radius: 8px;
            }
            .mobile-bottom-nav a.active {
                color: var(--brand);
                background: rgba(103, 81, 58, 0.08);
                opacity: 1;
            }
            .mobile-bottom-nav .nav-ico { font-size: 16px; line-height: 1; }
        }
    </style>
</head>
<body>
@hasSection('topbar')
    <header class="topbar">
        <div class="wrap">
            <div class="brand">Aduanefie<small>Rider Portal</small></div>
            <div class="who">
                @yield('topbar')
            </div>
        </div>
    </header>
@endif

<div class="wrap">
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

    @yield('content')
</div>

@hasSection('topbar')
    <nav class="mobile-bottom-nav" aria-label="Rider mobile navigation">
        <a href="{{ route('rider.dashboard') }}" class="{{ request()->routeIs('rider.dashboard') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true">📦</span>
            <span>Orders</span>
        </a>
        <a href="{{ route('rider.earnings') }}" class="{{ request()->routeIs('rider.earnings') ? 'active' : '' }}">
            <span class="nav-ico" aria-hidden="true">💳</span>
            <span>Earnings</span>
        </a>
    </nav>
@endif
</body>
</html>
