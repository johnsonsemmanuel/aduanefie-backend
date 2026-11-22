<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AggregationHub;
use App\Services\Markets\MarketProfileService;
use App\Services\Markets\ZoneAccessService;
use Illuminate\Http\Request;

class AggregationHubController extends Controller
{
    public function __construct(private MarketProfileService $markets, private ZoneAccessService $zoneAccess)
    {
    }

    /**
     * Customer-facing nearby hub discovery.
     *
     * A hub must be active, belong to a store in the customer's active zone,
     * and match the active supported market. A missing or unsupported country
     * is rejected rather than falling back to Ghana.
     */
    public function nearby(Request $request)
    {
        $profile = $this->markets->find($request->query('country_code'));
        if (!$profile) {
            return response()->json([
                'errors' => [[
                    'code' => 'unsupported_country',
                    'message' => 'Aggregation hubs are not available in your country.',
                ]],
            ], 403);
        }

        $zoneIds = $this->zoneAccess->resolveZoneIds(
            $request->header('zoneId') ?? $request->header('zoneid'),
            $profile['country_code']
        );
        if (!$zoneIds) {
            return response()->json([
                'errors' => [[
                    'code' => 'zone_mismatch',
                    'message' => translate('messages.zone_id_required'),
                ]],
            ], 403);
        }

        $coordinates = $this->validatedNearbyCoordinates($request);
        if (!$coordinates) {
            return $this->invalidNearbyCoordinatesResponse();
        }

        $radiusKm = max(1, min(100, (float) $request->query('radius_km', 25)));
        $limit = max(1, min(50, (int) $request->query('limit', 20)));
        $offset = max(1, (int) $request->query('offset', 1));
        $latitude = $coordinates['latitude'];
        $longitude = $coordinates['longitude'];
        $distanceSql = 'ACOS(
            COS(RADIANS(?)) * COS(RADIANS(latitude)) *
            COS(RADIANS(longitude) - RADIANS(?)) +
            SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        ) * 6371';

        $paginator = AggregationHub::query()
            ->active()
            ->where('country_code', $profile['country_code'])
            ->whereHas('store', function ($query) use ($zoneIds) {
                $query->whereIn('stores.zone_id', $zoneIds);
            })
            ->nearby($latitude, $longitude, $radiusKm)
            ->orderByRaw($distanceSql, [$latitude, $longitude, $latitude])
            ->paginate($limit, ['*'], 'page', $offset);

        $hubs = collect($paginator->items())->map(function (AggregationHub $hub) {
            return [
                'id' => $hub->id,
                'store_id' => $hub->store_id,
                'hub_code' => $hub->hub_code,
                'name' => $hub->name,
                'address' => $hub->address,
                'city' => $hub->city,
                'region' => $hub->region,
                'country_code' => $hub->country_code,
                'latitude' => $hub->latitude,
                'longitude' => $hub->longitude,
                'phone' => $hub->phone,
                'contact_person' => $hub->contact_person,
                'capacity_kg' => $hub->capacity_kg,
                'current_utilization_kg' => $hub->current_utilization_kg,
                'available_capacity_kg' => $hub->available_capacity_kg,
                'utilization_percent' => $hub->utilization_percent,
                'status' => $hub->status,
                'status_label' => $hub->status_label,
                'services' => $hub->services,
            ];
        })->values();

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'country_code' => $profile['country_code'],
            'radius_km' => $radiusKm,
            'hubs' => $hubs,
        ], 200);
    }

}
