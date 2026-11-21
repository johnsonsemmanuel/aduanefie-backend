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

        'TG' => [
            'name' => 'Togo',
            'networks' => [
                'MTN MoMo' => [
                    'label' => 'MTN MoMo Togo',
                    'pattern' => '^(228)?[279][0-9]{7}$',
                    'example' => '90XXXXXX',
                ],
                'Moov Money' => [
                    'label' => 'Moov Money Togo',
                    'pattern' => '^(228)?[679][0-9]{7}$',
                    'example' => '96XXXXXX',
                ],
            ],
        ],

        'CI' => [
            'name' => "Côte d'Ivoire",
            'networks' => [
                'MTN MoMo' => [
                    'label' => "MTN MoMo Côte d'Ivoire",
                    'pattern' => '^(225)?[0-9]{10}$',
                    'example' => '01XXXXXXXX',
                ],
                'Moov Money' => [
                    'label' => "Moov Money Côte d'Ivoire",
                    'pattern' => '^(225)?[0-9]{10}$',
                    'example' => '05XXXXXXXX',
                ],
                'Orange Money' => [
                    'label' => "Orange Money Côte d'Ivoire",
                    'pattern' => '^(225)?[0-9]{10}$',
                    'example' => '07XXXXXXXX',
                ],
            ],
        ],

        'BF' => [
            'name' => 'Burkina Faso',
            'networks' => [
                'MTN MoMo' => [
                    'label' => 'MTN MoMo Burkina Faso',
                    'pattern' => '^(226)?[567][0-9]{7}$',
                    'example' => '70XXXXXX',
                ],
                'Moov Money' => [
                    'label' => 'Moov Money Burkina Faso',
                    'pattern' => '^(226)?[567][0-9]{7}$',
                    'example' => '76XXXXXX',
                ],
                'Orange Money' => [
                    'label' => 'Orange Money Burkina Faso',
                    'pattern' => '^(226)?[567][0-9]{7}$',
                    'example' => '77XXXXXX',
                ],
            ],
        ],

        'SN' => [
            'name' => 'Senegal',
            'networks' => [
                'Orange Money' => [
                    'label' => 'Orange Money Senegal',
                    'pattern' => '^(221)?[367][0-9]{8}$',
                    'example' => '77XXXXXXX',
                ],
                'Wave' => [
                    'label' => 'Wave Senegal',
                    'pattern' => '^(221)?[367][0-9]{8}$',
                    'example' => '76XXXXXXX',
                ],
            ],
        ],

        'BJ' => [
            'name' => 'Benin',
            'networks' => [
                'MTN MoMo' => [
                    'label' => 'MTN MoMo Benin',
                    'pattern' => '^(229)?[0-9]{8}$',
                    'example' => '97XXXXXX',
                ],
                'Moov Money' => [
                    'label' => 'Moov Money Benin',
                    'pattern' => '^(229)?[0-9]{8}$',
                    'example' => '96XXXXXX',
                ],
            ],
        ],

        'ML' => [
            'name' => 'Mali',
            'networks' => [
                'Orange Money' => [
                    'label' => 'Orange Money Mali',
                    'pattern' => '^(223)?[5679][0-9]{7}$',
                    'example' => '77XXXXXX',
                ],
                'Moov Money' => [
                    'label' => 'Moov Money Mali',
                    'pattern' => '^(223)?[5679][0-9]{7}$',
                    'example' => '76XXXXXX',
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
