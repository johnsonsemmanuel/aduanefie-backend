<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CommunityZone;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class CommunityZoneController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|integer|exists:zones,id',
            'region' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $query = CommunityZone::query();

        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        $zones = $query->where('status', 1)->get();

        return response()->json($zones, 200);
    }

    public function byZone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|integer|exists:zones,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zones = CommunityZone::where('zone_id', $request->zone_id)
            ->where('status', 1)
            ->get();

        return response()->json($zones, 200);
    }
}
