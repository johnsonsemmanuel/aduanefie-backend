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
        'store.aduanefie.com' => 'store',
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
    | session_cookie Per-host session cookie name. Null keeps the default.
    | deny           Path patterns (Laravel $request->is()) that must 404.
    |
    | Hosts are privilege rings, not just branding:
    |   admin     -> sees everything (support can preview store/rider screens)
    |   store     -> may never see /admin
    |   logistics -> may never see /admin or /vendor-panel
    |   partners  -> may never see /admin or /vendor-panel
    |   api       -> HTML routes are denied entirely; only /api/* is served
    |
    | Denial is a 404, never a redirect: a redirect confirms the route exists.
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

        'store' => [
            'entry' => '/login/store',
            'session_cookie' => 'adf_store_session',
            'deny' => [
                'admin',
                'admin/*',
                'admin-login',
                'admin-mfa',
                'admin-mfa/*',
                'login/admin',
                'login/admin-*',
                // The rider portal is not a storefront surface.
                'rider',
                'rider/*',
                'rider-login',
                'rider-logout',
                // The marketer portal is not a storefront surface.
                'marketer',
                'marketer/*',
                'marketer-login',
                'marketer-mfa',
                'marketer-mfa/*',
            ],
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
