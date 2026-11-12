<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $site_direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Marketer Portal') · Aduanefie</title>
    <style>
        :root {
            --ink: var(--primary-clr, #67513A);
            --muted: #8B9F69;
            --line: #E0D6BD;
            --bg: #FAF8F5;
            --brand: var(--primary-clr, #67513A);
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
        .wrap { max-width: 980px; margin: 0 auto; padding: 0 16px 48px; }
        header.topbar { background: var(--ink); color: #fff; padding: 14px 0; margin-bottom: 20px; }
        header.topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-bottom: 0; }
        .brand { font-weight: 700; }
        .brand small { display: block; font-weight: 400; opacity: .7; font-size: 12px; }
        .who { font-size: 13px; text-align: right; opacity: .85; }
        nav.tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        nav.tabs a {
            text-decoration: none; padding: 8px 13px; border-radius: 9px;
            border: 1px solid var(--line); background: #fff; color: var(--ink); font-size: 14px;
        }
        nav.tabs a.active { background: var(--brand); border-color: var(--brand); color: #fff; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 18px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 17px; margin: 0 0 12px; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px; }
        .stat .label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .stat .value { font-size: 21px; font-weight: 700; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; border: 1px solid var(--line); background: #f8fafc; }
        .badge.pending { color: var(--warn); border-color: #f0d9a8; background: #fdf6e6; }
        .badge.eligible { color: var(--brand); border-color: #b9e0d1; background: #eaf6f1; }
        .badge.in_payout { color: var(--primary-clr, #67513A); border-color: #c7d7fb; background: #eef3ff; }
        .badge.paid { color: #166534; border-color: #c7e6cf; background: #eefaf1; }
        .badge.void, .badge.rejected { color: var(--danger); border-color: #f2c9c6; background: #fdf1f0; }
        .badge.requested, .badge.approved { color: var(--warn); border-color: #f0d9a8; background: #fdf6e6; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=tel], input[type=password], input[type=email], select, textarea {
            width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 10px;
            font-size: 15px; background: #fff; color: var(--ink); font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { outline: 2px solid rgba(11, 110, 79, .25); border-color: var(--brand); }
        .field { margin-bottom: 14px; }
        button, .btn {
            display: inline-block; padding: 11px 18px; border-radius: 10px; border: 1px solid var(--brand);
            background: var(--brand); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .btn.secondary { background: #fff; color: var(--ink); border-color: var(--line); }
        button.link { background: none; border: 0; color: #fff; padding: 0; font-size: 13px; text-decoration: underline; }
        .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert.error { background: #fdf1f0; border: 1px solid #f2c9c6; color: #7f1d1d; }
        .alert.ok { background: #eefaf1; border: 1px solid #c7e6cf; color: #14532d; }
        .captcha-row { display: flex; gap: 10px; align-items: center; }
        .captcha-row .captcha-img { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; line-height: 0; background: #fff; }
        .captcha-row img { height: 48px; width: auto; display: block; }
        .empty { color: var(--muted); font-size: 14px; padding: 8px 0; }
        .pager { display: flex; gap: 10px; margin-top: 14px; font-size: 14px; }
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
</body>
</html>
