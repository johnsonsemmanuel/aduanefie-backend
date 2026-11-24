<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * Canonical payout-rail lookup and validation.
 *
 * config/payout.php uses human keys ("Vodafone", "M-Pesa", "9mobile"). Every
 * form posts an uppercased value, so looking the raw config up by the posted
 * key rejected every network except MTN. Normalising keys in one place keeps
 * the config human-readable and the wire format stable: uppercase network,
 * uppercase ISO country, digits-only account number.
 */
class PayoutRails
{
    /**
     * Countries keyed by uppercase ISO code, networks keyed by uppercase key.
     *
     * @return array<string, array{name:string, networks:array<string, array<string,mixed>>}>
     */
    public static function countries(): array
    {
        $normalised = [];

        foreach ((array) config('payout.countries', []) as $code => $country) {
            $code = strtoupper(trim((string) $code));
            $networks = [];

            foreach ((array) ($country['networks'] ?? []) as $key => $network) {
                $networks[strtoupper(trim((string) $key))] = $network;
            }

            $normalised[$code] = array_merge($country, ['networks' => $networks]);
        }

        return $normalised;
    }

    /** @return array<string, string> country code => display name */
    public static function countryOptions(): array
    {
        return collect(self::countries())
            ->map(fn (array $country) => (string) ($country['name'] ?? ''))
            ->all();
    }

    /**
     * Network options for a country, keyed by the canonical uppercase value.
     *
     * @return array<string, string> network key => display label
     */
    public static function networkOptions(?string $country): array
    {
        if ($country === null || $country === '') {
            return [];
        }

        $config = self::countries()[strtoupper(trim($country))] ?? null;

        return collect($config['networks'] ?? [])
            ->map(fn (array $network) => (string) ($network['label'] ?? ''))
            ->all();
    }

    /**
     * Validate the rail and return the canonical values to persist.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $countries = self::countries();
        $country = strtoupper(trim((string) ($data['country'] ?? '')));
        $network = strtoupper(trim((string) ($data['network'] ?? '')));
        $account = preg_replace('/\D/', '', (string) ($data['account_number'] ?? '')) ?? '';

        $countryConfig = $countries[$country] ?? null;
        if (!$countryConfig) {
            throw self::failure('country', implode(',', array_keys($countries)),
                'The selected country is not supported for payouts yet.');
        }

        $networks = $countryConfig['networks'] ?? [];
        if (!array_key_exists($network, $networks)) {
            throw self::failure('network', implode(',', array_keys($networks)),
                'That network is not supported in ' . ($countryConfig['name'] ?? $country) . '.');
        }

        $pattern = $networks[$network]['pattern'] ?? null;
        if ($pattern && !preg_match('#' . $pattern . '#', $account)) {
            // config patterns are stored without regex delimiters, so the
            // `regex` validation rule would itself throw when building the
            // error message. Use a deliberately failing rule to carry the
            // friendly message instead.
            $validator = Validator::make(
                ['account_number' => (string) ($data['account_number'] ?? '')],
                ['account_number' => 'required|in:__invalid_rail_account__'],
                [
                    'account_number.in' => 'The account number does not look like a valid '
                        . ($networks[$network]['label'] ?? $network) . ' number for '
                        . ($countryConfig['name'] ?? $country) . '.',
                ]
            );
            throw new ValidationException($validator);
        }

        $data['country'] = $country;
        $data['network'] = $network;
        $data['account_number'] = $account;

        return $data;
    }

    /** @throws ValidationException */
    private static function failure(string $field, string $allowed, string $message): ValidationException
    {
        $validator = Validator::make(
            [$field => request()->input($field)],
            [$field => 'required|in:' . $allowed],
            [$field . '.in' => $message]
        );

        return new ValidationException($validator);
    }
}
