<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Validate a coordinate pair before it can be used for nearby ranking.
     * Missing, non-numeric, out-of-range and placeholder 0,0 values are
     * rejected so no endpoint can silently sort around an invented location.
     */
    protected function validatedCoordinatePair($latitude, $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }
        if (is_string($latitude)) {
            $latitude = trim($latitude);
        }
        if (is_string($longitude)) {
            $longitude = trim($longitude);
        }
        if ($latitude === '' || $longitude === '') {
            return null;
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if (!is_finite($latitude) || !is_finite($longitude)) {
            return null;
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }
        if ($latitude === 0.0 && $longitude === 0.0) {
            return null;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    protected function validatedNearbyCoordinates(Request $request): ?array
    {
        return $this->validatedCoordinatePair(
            $request->header('latitude'),
            $request->header('longitude'),
        );
    }

    protected function requestAsksForNearby(Request $request): bool
    {
        if ($request->query('quick_action') === 'nearby') {
            return true;
        }
        if ($request->query('sort_by') === 'nearby') {
            return true;
        }

        $filter = $request->query('filter');
        if (is_array($filter)) {
            return in_array('nearby', $filter, true);
        }
        if (is_string($filter)) {
            $values = array_map('trim', explode(',', trim($filter, "[] ")));
            return in_array('nearby', $values, true);
        }

        return false;
    }

    protected function invalidNearbyCoordinatesResponse()
    {
        return response()->json([
            'errors' => [[
                'code' => 'latitude-longitude',
                'message' => 'A valid location is required to show nearby results.',
            ]],
        ], 403);
    }
}
