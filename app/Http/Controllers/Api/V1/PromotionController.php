<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Empty-shape readers for the v4.2 storefront's marketing modules.
 *
 * The React storefront calls GET /bundle/home, /bogo/home and
 * /happy-hour/running on every home page. This backend is an older build and
 * has no bundle, BOGO or happy-hour tables, models, admin screens or cart
 * logic, so there is no data these endpoints could ever return. A 404 makes
 * the browser log an error on every load; the storefront already hides each
 * section when the payload is empty, so return the shape it expects and let
 * it hide the section.
 *
 * These are deliberate stubs, not a feature. Replace the bodies with real
 * queries when the modules land; the keys below are the ones the storefront
 * actually reads (bundles, is_live, is_running).
 */
class PromotionController extends Controller
{
    public function bundle_home(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }

        return response()->json([
            'data' => [],
            'bundles' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $limit,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
            'total_size' => 0,
            'limit' => $limit,
            'offset' => 1,
        ], 200);
    }

    public function bogo_home(Request $request)
    {
        return response()->json([
            'is_live' => false,
            'offer' => null,
            'description' => null,
            'bundles' => [],
        ], 200);
    }

    public function happy_hour_running(Request $request)
    {
        return response()->json([
            'is_running' => false,
            'happy_hour' => null,
        ], 200);
    }
}
