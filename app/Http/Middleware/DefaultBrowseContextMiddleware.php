<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\Zone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Ensures public read-only catalog browse endpoints have valid active zoneId
 * and moduleId defaults when a visitor has not selected a location/module yet
 * or holds a stale deactivated module ID from an older session.
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
        if (! $request->isMethod('GET') || ! $this->isPublicBrowsePath($request)) {
            return $next($request);
        }

        if (! $this->hasValidZoneHeader($request)) {
            $defaultZoneIds = Cache::remember('default_browse_zone_ids_v1', now()->addMinutes(5), function () {
                $ids = Zone::where('status', 1)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                return empty($ids) ? [1] : $ids;
            });

            $request->headers->set('zoneId', json_encode($defaultZoneIds));
            $request->headers->set('zoneid', json_encode($defaultZoneIds));
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

    private function hasValidZoneHeader(Request $request): bool
    {
        $raw = $request->header('zoneId');
        if ($raw === null || $raw === '' || strtolower((string) $raw) === 'null' || strtolower((string) $raw) === 'undefined') {
            return false;
        }

        $decoded = json_decode((string) $raw, true);
        if (is_numeric($decoded) && (int) $decoded > 0) {
            $request->headers->set('zoneId', json_encode([(int) $decoded]));
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
