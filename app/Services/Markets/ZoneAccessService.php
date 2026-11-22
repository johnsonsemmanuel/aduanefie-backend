<?php

namespace App\Services\Markets;

use App\CentralLogics\Helpers;
use App\Models\Zone;

/**
 * Resolves zones only inside a supported, explicitly identified market.
 *
 * A zone id is meaningless without its country: the same numeric id can belong
 * to a different market after data imports, and an old browser can keep a
 * stale zone across a country switch. This service is the single fail-closed
 * authority for parsing, validating and defaulting zone ids.
 */
class ZoneAccessService
{
    public function __construct(private readonly MarketProfileService $markets)
    {
    }

    /** Whether the caller explicitly declared a market (query or header). */
    public function isDeclared(?string $header = null, ?string $query = null): bool
    {
        foreach ([$query, $header] as $candidate) {
            if ($candidate !== null && trim((string) $candidate) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the market country. An explicitly declared but unsupported value
     * fails closed (returns null) and never falls back to the configured or
     * Ghana default. Only an absent declaration uses the business country.
     */
    public function resolveCountry(?string $header = null, ?string $query = null): ?string
    {
        foreach ([$query, $header] as $candidate) {
            if ($candidate !== null && trim((string) $candidate) !== '') {
                return $this->markets->normalizeCountry($candidate);
            }
        }

        $configured = Helpers::get_business_settings('country');

        return $this->markets->normalizeCountry(
            is_string($configured) || is_numeric($configured) ? (string) $configured : null
        );
    }

    /** Parse a scalar or JSON array zone header into positive integer ids. */
    public function parseZoneIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $value = trim((string) $raw);
            if ($value === '' || in_array(strtolower($value), ['null', 'undefined'], true)) {
                return [];
            }
            $decoded = json_decode($value, true);
            if (is_numeric($decoded)) {
                $decoded = [(int) $decoded];
            }
        }

        if (! is_array($decoded) || empty($decoded)) {
            return [];
        }

        $ids = array_map('intval', $decoded);
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));

        return $ids;
    }

    /**
     * Return the requested ids only when every one is active and belongs to
     * the resolved market. Any missing, inactive or foreign zone rejects the
     * whole request; there is no fallback to Ghana or to all active zones.
     */
    public function resolveZoneIds(mixed $raw, ?string $country): array
    {
        $ids = $this->parseZoneIds($raw);
        if (empty($ids) || ! $country) {
            return [];
        }

        $zones = Zone::active()->whereIn('id', $ids)->get(['id', 'country_code']);
        if ($zones->count() !== count($ids)) {
            return [];
        }

        foreach ($zones as $zone) {
            $zoneCountry = $this->markets->normalizeCountry(
                is_string($zone->country_code) ? $zone->country_code : null
            );

            if (! $zoneCountry || $zoneCountry !== $country) {
                return [];
            }
        }

        return $ids;
    }

    /** Default browse zones are market-scoped, never a global Ghana fallback. */
    public function defaultZoneIds(?string $country): array
    {
        if (! $country) {
            return [];
        }

        return Zone::active()
            ->where('country_code', $country)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
