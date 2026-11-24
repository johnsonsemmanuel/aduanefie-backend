<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payout rails per country
    |--------------------------------------------------------------------------
    |
    | Maps an ISO 3166-1 alpha-2 country code to the mobile-money networks
    | that marketers may request payouts to, plus the account-number pattern
    | each network expects. Used to validate payout requests server-side so a
    | marketer cannot enter an MTN Ghana number against a Kenyan country, or a
    | landline-looking string as a mobile-money account.
    |
    | Each network entry:
    |   label       - human-readable name shown in validation hints
    |   pattern     - preg regex the account number must match (digits only,
    |                 optional leading + or 00 country prefix stripped first)
    |   example     - placeholder text for the form
    */

    'countries' => [

        'GH' => [
            'name' => 'Ghana',
            'networks' => [
                'MTN' => [
                    'label' => 'MTN Mobile Money',
                    'pattern' => '^(233|0)?(24|54|55|59)[0-9]{7}$',
                    'example' => '024XXXXXXXX',
                ],
                'Vodafone' => [
                    'label' => 'Vodafone Cash',
                    'pattern' => '^(233|0)?(20|50)[0-9]{7}$',
                    'example' => '020XXXXXXXX',
                ],
                'AirtelTigo' => [
                    'label' => 'AirtelTigo Money',
                    'pattern' => '^(233|0)?(27|57)[0-9]{7}$',
                    'example' => '027XXXXXXXX',
                ],
            ],
        ],

        'NG' => [
            'name' => 'Nigeria',
            'networks' => [
                'MTN' => [
                    'label' => 'MTN Nigeria',
                    'pattern' => '^(234|0)?(803|806|813|814|816|903|906|913|916)[0-9]{7}$',
                    'example' => '0803XXXXXXXX',
                ],
                'Airtel' => [
                    'label' => 'Airtel Nigeria',
                    'pattern' => '^(234|0)?(802|808|812|902|904|907|912)[0-9]{7}$',
                    'example' => '0802XXXXXXXX',
                ],
                'Glo' => [
                    'label' => 'Glo Nigeria',
                    'pattern' => '^(234|0)?(805|807|811|905|915)[0-9]{7}$',
                    'example' => '0805XXXXXXXX',
                ],
                '9mobile' => [
                    'label' => '9mobile',
                    'pattern' => '^(234|0)?(809|817|908|909)[0-9]{7}$',
                    'example' => '0809XXXXXXXX',
                ],
            ],
        ],

        'KE' => [
            'name' => 'Kenya',
            'networks' => [
                'M-Pesa' => [
                    'label' => 'M-Pesa (Safaricom)',
                    'pattern' => '^(254|0)?(7|1)[0-9]{8}$',
                    'example' => '07XXXXXXXX',
                ],
                'Airtel Money' => [
                    'label' => 'Airtel Money Kenya',
                    'pattern' => '^(254|0)?(7|1)[0-9]{8}$',
                    'example' => '07XXXXXXXX',
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default country
    |--------------------------------------------------------------------------
    |
    | Used when the marketer's account has no country set and none is provided.
    */

    'default_country' => 'GH',

];
