<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Issuer is what the authenticator app shows next to the account. Pending
    | TTL bounds how long a password-verified session may sit on the challenge
    | screen before the password has to be entered again. Session TTL bounds
    | how long a completed MFA session lasts, so a stolen session cookie is not
    | valid forever.
    |
    */

    'issuer' => env('ADMIN_MFA_ISSUER', 'Aduanefie'),

    // Adjacent 30-second steps accepted either side, for clock drift.
    'window' => 1,

    // Seconds a password-verified, not-yet-MFA session may live.
    'pending_ttl' => 300,

    // Seconds a completed MFA session may live (12 hours).
    'session_ttl' => 43200,

    // Number of one-time recovery codes issued at enrolment.
    'recovery_codes' => 8,

];
