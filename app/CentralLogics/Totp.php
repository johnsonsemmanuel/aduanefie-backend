<?php

namespace App\CentralLogics;

/**
 * RFC 6238 TOTP and RFC 4648 base32, implemented locally.
 *
 * There is no 2FA package in composer.json and the admin panel is the only
 * consumer, so a small, auditable implementation beats adding a dependency
 * that nobody else in the codebase uses. SHA-1 / 6 digits / 30 seconds is what
 * every authenticator app defaults to.
 */
class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * New random secret, base32-encoded (160 bits, the RFC 4226 recommendation).
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * Check a submitted code and return the matching time-step, or null.
     *
     * Returning the step (rather than a bool) lets the caller record it and
     * refuse to accept the same step twice. $window is the number of adjacent
     * 30-second steps to tolerate either side, for clock drift.
     */
    public static function verify(string $secret, ?string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }

        $key = self::base32Decode($secret);
        if ($key === '') {
            return null;
        }

        $current = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $current + $offset;
            if ($step < 0) {
                continue;
            }
            if (hash_equals(self::codeForStep($key, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * The code for one specific time-step. Exposed for tests and for tooling.
     */
    public static function codeForStep(string $binaryKey, int $step): string
    {
        // 'J' is an unsigned 64-bit big-endian integer.
        $hash = hash_hmac('sha1', pack('J', $step), $binaryKey, true);

        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The current code for a secret. Used by the admin:mfa-reset tooling and
     * by the verification harness; never by the login flow.
     */
    public static function currentCode(string $secret, ?int $timestamp = null): string
    {
        return self::codeForStep(
            self::base32Decode($secret),
            intdiv($timestamp ?? time(), self::PERIOD)
        );
    }

    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    public static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded));
        if ($encoded === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($encoded) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
