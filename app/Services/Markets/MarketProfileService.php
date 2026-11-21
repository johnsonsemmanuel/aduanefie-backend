<?php

namespace App\Services\Markets;

use App\Traits\PaymentGatewayTrait;
use InvalidArgumentException;

/**
 * Resolves the authoritative market profile for a country.
 *
 * The frontend mirrors these profiles for rendering, but this service is the
 * server-side authority for currency, gateway and identity rules. Unsupported
 * countries and unsupported currency/gateway pairs throw instead of silently
 * falling back to Ghana.
 */
class MarketProfileService
{
    use PaymentGatewayTrait;

    public function all(): array
    {
        return (array) config('market_profiles.profiles', []);
    }

    public function defaultCountry(): string
    {
        return strtoupper((string) config('market_profiles.default_country', 'GH'));
    }

    public function normalizeCountry(?string $country): ?string
    {
        $value = trim((string) $country);
        if ($value === '') {
            return null;
        }

        $needle = strtoupper($value);
        $profiles = $this->all();

        if (isset($profiles[$needle])) {
            return $needle;
        }

        foreach ($profiles as $code => $profile) {
            $aliases = array_merge([(string) $profile['name'], $code], (array) ($profile['aliases'] ?? []));
            foreach ($aliases as $alias) {
                if (strtoupper((string) $alias) === $needle) {
                    return (string) $code;
                }
            }
        }

        return null;
    }

    public function resolve(?string $country): array
    {
        $code = $this->normalizeCountry($country ?: $this->defaultCountry());

        if (! $code) {
            throw new InvalidArgumentException('Unsupported country. Please choose a supported market.');
        }

        return array_merge(['country_code' => $code], $this->all()[$code]);
    }

    public function find(?string $country): ?array
    {
        $code = $this->normalizeCountry($country);

        return $code ? array_merge(['country_code' => $code], $this->all()[$code]) : null;
    }

    public function currencyForCountry(?string $country): ?string
    {
        $profile = $this->find($country);

        return $profile ? strtoupper((string) $profile['currency_code']) : null;
    }

    public function countryForCurrency(string $currency): ?string
    {
        $currency = strtoupper($currency);

        foreach ($this->all() as $code => $profile) {
            if (strtoupper((string) $profile['currency_code']) === $currency) {
                return (string) $code;
            }
        }

        return null;
    }

    public function currencyAllowed(?string $country, string $currency): bool
    {
        $profile = $this->find($country);
        if (! $profile) {
            return false;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', (array) $profile['payments']['presentment_currencies']), true);
    }

    public function gatewayAllowed(?string $country, string $gateway): bool
    {
        $profile = $this->find($country);
        if (! $profile) {
            return false;
        }

        return in_array(strtolower($gateway), array_map('strtolower', (array) $profile['payments']['gateways']), true);
    }

    public function gatewaySupportsCurrency(string $gateway, string $currency): bool
    {
        $supported = static::getPaymentGatewaySupportedCurrencies(strtolower($gateway));

        return isset($supported[strtoupper($currency)]);
    }

    /**
     * Fail-closed check used before a payment request is sent to a gateway.
     */
    public function assertPayable(?string $country, string $gateway, string $currency): void
    {
        if (! $this->currencyAllowed($country, $currency)) {
            throw new InvalidArgumentException('The order currency is not supported in this market.');
        }

        if (! $this->gatewayAllowed($country, $gateway)) {
            throw new InvalidArgumentException('This payment method is not available in this market.');
        }

        if (! $this->gatewaySupportsCurrency($gateway, $currency)) {
            throw new InvalidArgumentException('This payment method cannot charge the order currency.');
        }
    }

    /**
     * Public API shape. Identity hints are safe to expose; no document images
     * or full identifiers are ever stored by this service.
     */
    public function publicProfile(?string $country): array
    {
        $profile = $this->resolve($country);

        return [
            'country_code' => $profile['country_code'],
            'name' => $profile['name'],
            'locale' => $profile['locale'],
            'currency_code' => $profile['currency_code'],
            'currency_symbol' => $profile['currency_symbol'],
            'decimal_places' => (int) $profile['decimal_places'],
            'phone' => $profile['phone'],
            'address' => $profile['address'],
            'identity' => [
                'label' => $profile['identity']['label'],
                'last4_label' => $profile['identity']['last4_label'],
                'hint' => $profile['identity']['hint'],
                'verification_available' => (bool) $profile['identity']['verification_available'],
            ],
            'payments' => [
                'gateways' => $profile['payments']['gateways'],
                'presentment_currencies' => $profile['payments']['presentment_currencies'],
            ],
            'payouts' => $profile['payouts'],
            'units' => $profile['units'],
            'support' => $profile['support'],
        ];
    }
}
