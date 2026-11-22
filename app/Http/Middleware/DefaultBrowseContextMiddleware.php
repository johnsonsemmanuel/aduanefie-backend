<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\Zone;
use App\Services\Markets\ZoneAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Ensures API requests that carry a zoneId use one that belongs to the
 * declared market, and that public browse endpoints have valid active
 * zoneId/moduleId defaults. A client that declares X-Market-Country is
 * validated fail-closed: a stale or foreign zone is rejected instead of being
 * silently scoped to another country's data.
 */
class DefaultBrowseContextMiddleware
{
    private const PUBLIC_BROWSE_PATTERNS = [
        'api/v1/banners*',
        'api/v1/other-banners*',
        'api/v1/items/*',
        'api/v1/stores/*',
        'api/v1/categories*',
        'api/v1/common-condition*',
        'api/v1/brand*',
        'api/v1/campaigns*',
        'api/v1/flash-sales*',
        'api/v1/advertisement*',
        'api/v1/coupon/list*',
        'api/v1/get-combined-data',
    ];

    public function handle(Request $request, Closure $next)
    {
        /** @var ZoneAccessService $zoneAccess */
        $zoneAccess = app(ZoneAccessService::class);
        $countryHeader = $request->header('X-Market-Country');
        $countryQuery = $request->query('country_code');
        $declared = $zoneAccess->isDeclared($countryHeader, $countryQuery);
        $country = $declared
            ? $zoneAccess->resolveCountry($countryHeader, $countryQuery)
            : $zoneAccess->resolveCountry();

        // A declared market makes the zone header authoritative. Validate it
        // on every API request, not just browse GETs, so checkout/order/zone
        // update paths cannot persist a stale zone from another country.
        if ($declared && $this->hasZoneHeader($request)) {
            $validated = $zoneAccess->resolveZoneIds($this->zoneHeader($request), $country);
            if (empty($validated)) {
                return response()->json([
                    'errors' => [[
                        'code' => 'zone_mismatch',
                        'message' => 'The selected location does not belong to the current market.',
                    ]],
                ], 403);
            }
            $this->setZoneHeaders($request, $validated);
        }

        if (! $request->isMethod('GET') || ! $this->isPublicBrowsePath($request)) {
            return $next($request);
        }

        if ($declared) {
            if ($country === null) {
                // Unsupported/unknown market: never synthesise a zone.
                $request->headers->remove('zoneId');
                $request->headers->remove('zoneid');
            } elseif (! $this->hasZoneHeader($request)) {
                $defaults = $zoneAccess->defaultZoneIds($country);
                if (! empty($defaults)) {
                    $this->setZoneHeaders($request, $defaults);
                }
                // No zones for this market yet: leave the header absent so the
                // controller returns its empty/unsupported state, not Ghana.
            }
        } elseif (! $this->hasValidZoneHeader($request)) {
            // Legacy callers without a market declaration keep the previous
            // behaviour: all active zones as the default browse context.
            $defaultZoneIds = Cache::remember('default_browse_zone_ids_v1', now()->addMinutes(5), function () {
                $ids = Zone::where('status', 1)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                return empty($ids) ? [1] : $ids;
            });

            $this->setZoneHeaders($request, $defaultZoneIds);
        }

        $activeModules = Cache::remember('active_modules_by_id_v1', now()->addMinutes(5), function () {
            return Module::active()->orderBy('id')->get()->keyBy('id');
        });

        if ($activeModules->isNotEmpty()) {
            $requestedModuleId = (int) $request->header('moduleId', 0);
            $exceptNoModule = $request->is('api/v1/banners')
                || $request->is('api/v1/stores/get-stores/*')
                || $request->is('api/v1/categories')
                || $request->is('api/v1/coupon/list');

            if ($requestedModuleId > 0 && ! $activeModules->has($requestedModuleId)) {
                $fallbackId = (string) $activeModules->first()->id;
                $request->headers->set('moduleId', $fallbackId);
            } elseif (! $request->hasHeader('moduleId') && ! $exceptNoModule) {
                $fallbackId = (string) $activeModules->first()->id;
                $request->headers->set('moduleId', $fallbackId);
            }
        }

        return $next($request);
    }

    private function isPublicBrowsePath(Request $request): bool
    {
        foreach (self::PUBLIC_BROWSE_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @return string|null */
    private function zoneHeader(Request $request): ?string
    {
        return $request->header('zoneId') ?? $request->header('zoneid');
    }

    private function hasZoneHeader(Request $request): bool
    {
        $raw = $this->zoneHeader($request);
        if ($raw === null || $raw === '') {
            return false;
        }

        return ! in_array(strtolower((string) $raw), ['null', 'undefined', 'nan'], true);
    }

    /** @param array<int> $ids */
    private function setZoneHeaders(Request $request, array $ids): void
    {
        $encoded = json_encode(array_values(array_map('intval', $ids)));
        $request->headers->set('zoneId', $encoded);
        $request->headers->set('zoneid', $encoded);
    }

    private function hasValidZoneHeader(Request $request): bool
    {
        $raw = $this->zoneHeader($request);
        if ($raw === null || $raw === '' || in_array(strtolower((string) $raw), ['null', 'undefined', 'nan'], true)) {
            return false;
        }

        $decoded = json_decode((string) $raw, true);
        if (is_numeric($decoded) && (int) $decoded > 0) {
            $request->headers->set('zoneId', json_encode([(int) $decoded]));
            $request->headers->set('zoneid', json_encode([(int) $decoded]));
            return true;
        }

        if (! is_array($decoded) || empty($decoded)) {
            return false;
        }

        $numericIds = array_values(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0));
        if (empty($numericIds)) {
            return false;
        }

        return true;
    }
}
