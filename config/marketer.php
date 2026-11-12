<?php

return [
    /*
    | Which admin role is a remote marketer.
    | 5 = "Remote Marketer" in admin_roles.
    */
    'role_id' => 5,

    /*
    | Share of the platform commission a marketer earns, in percent.
    | A per-marketer value on admins.marketer_commission_rate overrides this.
    */
    'default_rate' => (float) env('MARKETER_DEFAULT_RATE', 20),

    /*
    | Days after delivery before a commission can be paid. Covers the refund
    | window; a commission is not requestable before this elapses.
    */
    'holding_days' => (int) env('MARKETER_HOLDING_DAYS', 14),

    /*
    | Smallest payout a marketer may request, in the ledger currency. 0 disables
    | the check.
    */
    'min_payout' => (float) env('MARKETER_MIN_PAYOUT', 0),

    /*
    | Ledger currency. Null reads business_settings.currency at accrual time.
    */
    'currency' => env('MARKETER_CURRENCY'),
];
