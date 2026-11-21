<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Market profiles
    |--------------------------------------------------------------------------
    |
    | One profile per country drives country-aware behaviour in the API and is
    | mirrored by the frontend's market profile utility. A country without a
    | profile is unsupported and must show an explicit state, never a silent
    | fallback to Ghana.
    |
    | verification_available = false must block the verified-organiser badge;
    | it must never downgrade to a weaker identity check.
    |
    */

    'default_country' => env('MARKET_DEFAULT_COUNTRY', 'GH'),

    'fx_base_currency' => 'USD',

    'fx_currencies' => ['USD', 'GHS', 'NGN', 'XOF', 'KES', 'ZAR'],

    'profiles' => [

        'GH' => [
            'name' => 'Ghana',
            'locale' => 'en-GH',
            'currency_code' => 'GHS',
            'currency_symbol' => 'GH₵',
            'decimal_places' => 2,
            'phone' => [
                'dial_code' => '+233',
                'example' => '0241234567',
                'pattern' => '/^(?:\+?233|0)?[2-5][0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'Region',
                'district_label' => 'District / MMDA',
                'city_label' => 'Town / City',
                'postal_code_label' => 'Postal code (optional)',
                'location_dataset' => 'ghana',
            ],
            'identity' => [
                'label' => 'Ghana Card',
                'last4_label' => 'Ghana Card last 4 characters',
                'hint' => 'Only the last 4 characters are stored. Never share your full card number here.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['hubtel', 'paystack', 'flutterwave'],
                'presentment_currencies' => ['GHS'],
            ],
            'payouts' => [
                'currency_code' => 'GHS',
                'networks' => ['MTN', 'Vodafone', 'AirtelTigo'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'tin', 'bag', 'crate']],
            'support' => ['whatsapp_dial_code' => '233', 'locale' => 'en-GH'],
            'aliases' => ['GHANA', 'GH'],
        ],

        'NG' => [
            'name' => 'Nigeria',
            'locale' => 'en-NG',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'decimal_places' => 2,
            'phone' => [
                'dial_code' => '+234',
                'example' => '08031234567',
                'pattern' => '/^(?:\+?234|0)?[789][01][0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'State',
                'district_label' => 'Local Government Area',
                'city_label' => 'City / Town',
                'postal_code_label' => 'Postal code (optional)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Nigerian National ID (NIN)',
                'last4_label' => 'National ID last 4 characters',
                'hint' => 'Use the last 4 characters of your NIN, driver licence or voter card reference. Only these 4 characters are stored.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack', 'flutterwave'],
                'presentment_currencies' => ['NGN'],
            ],
            'payouts' => [
                'currency_code' => 'NGN',
                'networks' => ['MTN', 'Airtel', 'Glo', '9mobile'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'tin', 'bag', 'crate', 'keg']],
            'support' => ['whatsapp_dial_code' => '234', 'locale' => 'en-NG'],
            'aliases' => ['NIGERIA', 'NG'],
        ],

        'TG' => [
            'name' => 'Togo',
            'locale' => 'fr-TG',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+228',
                'example' => '90123456',
                'pattern' => '/^(?:\+?228)?[279][0-9]{7}$/',
            ],
            'address' => [
                'region_label' => 'Région',
                'district_label' => 'Préfecture',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CIN)',
                'last4_label' => 'CIN : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['MTN MoMo', 'Moov Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '228', 'locale' => 'fr-TG'],
            'aliases' => ['TOGO', 'TG'],
        ],

        'CI' => [
            'name' => "Côte d'Ivoire",
            'locale' => 'fr-CI',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+225',
                'example' => '0123456789',
                'pattern' => '/^(?:\+?225)?[0-9]{10}$/',
            ],
            'address' => [
                'region_label' => 'District',
                'district_label' => 'Région',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CNI)',
                'last4_label' => 'CNI : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['MTN MoMo', 'Moov Money', 'Orange Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '225', 'locale' => 'fr-CI'],
            'aliases' => ["CÔTE D'IVOIRE", "COTE D'IVOIRE", 'IVORY COAST', 'CI'],
        ],

        'BF' => [
            'name' => 'Burkina Faso',
            'locale' => 'fr-BF',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+226',
                'example' => '70123456',
                'pattern' => '/^(?:\+?226)?[567][0-9]{7}$/',
            ],
            'address' => [
                'region_label' => 'Région',
                'district_label' => 'Province',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CNI)',
                'last4_label' => 'CNI : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['MTN MoMo', 'Moov Money', 'Orange Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '226', 'locale' => 'fr-BF'],
            'aliases' => ['BURKINA FASO', 'BF'],
        ],

        'SN' => [
            'name' => 'Senegal',
            'locale' => 'fr-SN',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+221',
                'example' => '771234567',
                'pattern' => '/^(?:\+?221)?[367][0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'Région',
                'district_label' => 'Département',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CNI)',
                'last4_label' => 'CNI : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['Orange Money', 'Wave', 'Free Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '221', 'locale' => 'fr-SN'],
            'aliases' => ['SENEGAL', 'SN'],
        ],

        'BJ' => [
            'name' => 'Benin',
            'locale' => 'fr-BJ',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+229',
                'example' => '97123456',
                'pattern' => '/^(?:\+?229)?[0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'Département',
                'district_label' => 'Commune',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CNI)',
                'last4_label' => 'CNI : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['MTN MoMo', 'Moov Money', 'Orange Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '229', 'locale' => 'fr-BJ'],
            'aliases' => ['BENIN', 'BJ'],
        ],

        'ML' => [
            'name' => 'Mali',
            'locale' => 'fr-ML',
            'currency_code' => 'XOF',
            'currency_symbol' => 'CFA',
            'decimal_places' => 0,
            'phone' => [
                'dial_code' => '+223',
                'example' => '70123456',
                'pattern' => '/^(?:\+?223)?[5679][0-9]{7}$/',
            ],
            'address' => [
                'region_label' => 'Région',
                'district_label' => 'Cercle',
                'city_label' => 'Ville',
                'postal_code_label' => 'Code postal (facultatif)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'Carte nationale d’identité (CNI)',
                'last4_label' => 'CNI : 4 derniers caractères',
                'hint' => 'Seuls ces 4 caractères sont enregistrés. Ne saisissez jamais votre numéro complet.',
                'verification_available' => true,
            ],
            'payments' => [
                'gateways' => ['paystack'],
                'presentment_currencies' => ['XOF'],
            ],
            'payouts' => [
                'currency_code' => 'XOF',
                'networks' => ['Orange Money', 'Moov Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'bidon', 'sac', 'caisse']],
            'support' => ['whatsapp_dial_code' => '223', 'locale' => 'fr-ML'],
            'aliases' => ['MALI', 'ML'],
        ],

        'KE' => [
            'name' => 'Kenya',
            'locale' => 'sw-KE',
            'currency_code' => 'KES',
            'currency_symbol' => 'Ksh',
            'decimal_places' => 2,
            'phone' => [
                'dial_code' => '+254',
                'example' => '0712345678',
                'pattern' => '/^(?:\+?254|0)?[17][0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'County',
                'district_label' => 'Sub-county',
                'city_label' => 'Town / City',
                'postal_code_label' => 'Postal code (optional)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'National ID card',
                'last4_label' => 'National ID last 4 characters',
                'hint' => 'Only the last 4 characters are stored. Never share your full ID number here.',
                'verification_available' => false,
            ],
            'payments' => [
                'gateways' => ['paystack', 'flutterwave'],
                'presentment_currencies' => ['KES'],
            ],
            'payouts' => [
                'currency_code' => 'KES',
                'networks' => ['M-Pesa', 'Airtel Money'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'tin', 'bag', 'crate']],
            'support' => ['whatsapp_dial_code' => '254', 'locale' => 'sw-KE'],
            'aliases' => ['KENYA', 'KE'],
        ],

        'ZA' => [
            'name' => 'South Africa',
            'locale' => 'en-ZA',
            'currency_code' => 'ZAR',
            'currency_symbol' => 'R',
            'decimal_places' => 2,
            'phone' => [
                'dial_code' => '+27',
                'example' => '0712345678',
                'pattern' => '/^(?:\+?27|0)?[6-8][0-9]{8}$/',
            ],
            'address' => [
                'region_label' => 'Province',
                'district_label' => 'Municipality',
                'city_label' => 'City / Town',
                'postal_code_label' => 'Postal code (required)',
                'location_dataset' => 'manual',
            ],
            'identity' => [
                'label' => 'South African ID',
                'last4_label' => 'ID last 4 characters',
                'hint' => 'Only the last 4 characters are stored. Never share your full ID number here.',
                'verification_available' => false,
            ],
            'payments' => [
                'gateways' => ['paystack', 'flutterwave', 'payfast'],
                'presentment_currencies' => ['ZAR'],
            ],
            'payouts' => [
                'currency_code' => 'ZAR',
                'networks' => ['bank_transfer'],
            ],
            'units' => ['weight' => 'kg', 'volume' => 'litre', 'packaging' => ['sachet', 'tin', 'bag', 'crate']],
            'support' => ['whatsapp_dial_code' => '27', 'locale' => 'en-ZA'],
            'aliases' => ['SOUTH AFRICA', 'ZA'],
        ],
    ],
];
