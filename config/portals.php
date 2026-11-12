<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal host map
    |--------------------------------------------------------------------------
    |
    | One codebase, several front doors. Every host below is the *same* Laravel
    | application; this map only decides which front door a request arrived at.
    | Hosts not listed here fall back to the "default" portal, which has no
    | restrictions, so local development (localhost / 127.0.0.1) is unaffected.
    |
    */

    'default' => 'default',

    'hosts' => [
        'admin.aduanefie.com' => 'admin',
        // Retired host aliases. Both used to point at a second Laravel install;
        // they now exist only to send browsers to the ops host.
        'store.aduanefie.com' => 'store',
        'vendor.aduanefie.com' => 'store',
        'logistics.aduanefie.com' => 'logistics',
        'partners.aduanefie.com' => 'partners',
        'api.aduanefie.com' => 'api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal rules
    |--------------------------------------------------------------------------
    |
    | entry          Where GET / should land. Null means "leave it alone".
    | redirect_to    Retired host: send every GET to this origin with the path
    |                preserved, and refuse every other method. Null disables it.
    | redirect_path  Where the root of a retired host lands.
    | session_cookie Per-host session cookie name. Null keeps the default.
    | deny           Path patterns (Laravel $request->is()) that must 404.
    |
    | Hosts are privilege rings, not just branding:
    |   admin     -> sees everything (support can preview store/rider screens)
    |   store     -> retired; 301s to the admin store login
    |   logistics -> may never see /admin or /vendor-panel
    |   partners  -> may never see /admin or /vendor-panel
    |   api       -> HTML routes are denied entirely; only /api/* is served
    |
    | Denial is a 404, never a redirect: a redirect confirms the route exists.
    | The retired-host redirect is the one exception, because the whole host has
    | moved and there is no route left to confirm.
    |
    */

    'portals' => [

        'default' => [
            'entry' => null,
            'session_cookie' => null,
            'deny' => [],
        ],

        'admin' => [
            'entry' => '/admin-login',
            'session_cookie' => 'adf_admin_session',
            'deny' => [],
        ],

        // store. and vendor. are retired. The store portal lives at
        // admin.aduanefie.com/store-login; deep links keep their path so old
        // bookmarks such as /login/store still resolve on the ops host.
        'store' => [
            'entry' => null,
            'redirect_to' => 'https://admin.aduanefie.com',
            'redirect_path' => '/store-login',
            'session_cookie' => null,
            'deny' => [],
        ],

        'logistics' => [
            'entry' => '/rider-login',
            'session_cookie' => 'adf_logistics_session',
            'deny' => [
                'admin',
                'admin/*',
                'admin-login',
                'admin-mfa',
                'admin-mfa/*',
                'vendor-panel',
                'vendor-panel/*',
                'login/admin',
                'login/admin-*',
                'login/store',
                'login/store-*',
                'store-login',
                'store-login/*',
                'marketer',
                'marketer/*',
                'marketer-login',
                'marketer-mfa',
                'marketer-mfa/*',
            ],
        ],

        'partners' => [
            'entry' => '/marketer-login',
            'session_cookie' => 'adf_partners_session',
            'deny' => [
                'admin',
                'admin/*',
                'admin-login',
                'admin-mfa',
                'admin-mfa/*',
                'vendor-panel',
                'vendor-panel/*',
                'login/admin',
                'login/admin-*',
                'login/store',
                'login/store-*',
                'store-login',
                'store-login/*',
                'rider',
                'rider/*',
                'rider-login',
                'rider-logout',
            ],
        ],

        'api' => [
            'entry' => null,
            'session_cookie' => 'adf_api_session',
            'deny' => [
                'admin',
                'admin/*',
                'admin-login',
                'admin-mfa',
                'admin-mfa/*',
                'vendor-panel',
                'vendor-panel/*',
                'login/*',
                'login_submit',
                'store-login',
                'store-login/*',
                'rider',
                'rider/*',
                'rider-login',
                'rider-logout',
                'marketer',
                'marketer/*',
                'marketer-login',
                'marketer-mfa',
                'marketer-mfa/*',
            ],
        ],

    ],

];
