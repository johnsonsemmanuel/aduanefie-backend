<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative feature management backed by `business_settings` (key: `feature_flags`).
 *
 * Separates five operational dimensions per feature:
 *  - public_visible: whether public navigation/footer/cards advertise the feature
 *  - route_enabled: whether public pages/routes for the feature are accessible
 *  - allow_new_transactions: whether new orders/commitments/applications can be initiated
 *  - allow_historical_access: whether existing/past records remain readable
 *  - allow_background_queries: whether optional homepage/ticker polling queries run
 */
class FeatureFlagService
{
    public const SETTING_KEY = 'feature_flags';
    private const CACHE_KEY = 'aduanefie_feature_flags_v1';

    /**
     * Baseline defaults. Domestic wholesale is independent of cross-border trade.
     * New RFQ commitments default to disabled until an approved Order or
     * Admin-assisted fulfilment workflow is connected to awarded quotes.
     *
     * @return array<string, array<string, bool>>
     */
    public static function defaults(): array
    {
        return [
            'domestic_wholesale' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'cross_border_trade' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'rfq_new_commitments' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => false,
                'allow_historical_access' => true,
                'allow_background_queries' => false,
            ],
            'live_farming' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'group_buys' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'agro_prices' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => false,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'bogo_deals' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => true,
            ],
            'regional_tours' => [
                'public_visible' => true,
                'route_enabled' => true,
                'allow_new_transactions' => true,
                'allow_historical_access' => true,
                'allow_background_queries' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function all(): array
    {
        $defaults = self::defaults();

        if (!Schema::hasTable('business_settings')) {
            return $defaults;
        }

        $raw = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            return DB::table('business_settings')->where('key', self::SETTING_KEY)->value('value');
        });

        $overrides = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $overrides = $decoded;
            }
        } elseif (is_array($raw)) {
            $overrides = $raw;
        }

        foreach ($defaults as $feature => $dims) {
            if (!isset($overrides[$feature])) {
                continue;
            }
            if (is_bool($overrides[$feature])) {
                // Shorthand boolean toggles visibility, route, transactions and background queries,
                // while preserving historical access by default.
                $enabled = $overrides[$feature];
                $defaults[$feature]['public_visible'] = $enabled;
                $defaults[$feature]['route_enabled'] = $enabled;
                $defaults[$feature]['allow_new_transactions'] = $enabled;
                $defaults[$feature]['allow_background_queries'] = $enabled;
                continue;
            }
            if (is_array($overrides[$feature])) {
                foreach ($dims as $dim => $defaultVal) {
                    if (array_key_exists($dim, $overrides[$feature])) {
                        $defaults[$feature][$dim] = (bool) $overrides[$feature][$dim];
                    }
                }
            }
        }

        return $defaults;
    }

    public function flag(string $feature, string $dimension = 'public_visible'): bool
    {
        $all = $this->all();
        return (bool) ($all[$feature][$dimension] ?? false);
    }

    public function allowsNewTransactions(string $feature): bool
    {
        return $this->flag($feature, 'allow_new_transactions');
    }

    public function allowsHistoricalAccess(string $feature): bool
    {
        return $this->flag($feature, 'allow_historical_access');
    }

    public function isPubliclyVisible(string $feature): bool
    {
        return $this->flag($feature, 'public_visible');
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
