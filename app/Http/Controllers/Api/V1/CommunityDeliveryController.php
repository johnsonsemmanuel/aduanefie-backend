<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CommunityZone;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityDeliveryController extends Controller
{
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_address_id' => 'required|integer|exists:customer_addresses,id',
            'zone_id' => 'required|integer|exists:zones,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $available = CommunityZone::where('zone_id', $request->zone_id)
            ->where('status', 1)
            ->exists();

        $fee = $available ? 5.00 : 0;
        $eta = $available ? '30-60 min' : null;

        return response()->json([
            'available' => $available,
            'fee' => $fee,
            'eta' => $eta,
        ], 200);
    }
}
