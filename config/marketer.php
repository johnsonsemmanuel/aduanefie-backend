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
    | Fixed onboarding bounty (in GHS) a marketer earns for each verified
    | farmer / stakeholder store they bring onto the platform.
    | A per-marketer value on admins.onboarding_bounty_ghs overrides this.
    */
    'default_onboarding_bounty' => (float) env('MARKETER_DEFAULT_ONBOARDING_BOUNTY', 25),

    /*
    | Days after delivery before a commission can be paid. Covers the refund
    | window; a commission is not requestable before this elapses.
    */
    'holding_days' => (int) env('MARKETER_HOLDING_DAYS', 14),

    /*
    | Whether 2FA/MFA is mandatory for field marketers. False allows direct login
    | with email and password so field agents in rural areas are not locked out.
    */
    'mfa_required' => (bool) env('MARKETER_MFA_REQUIRED', false),

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
