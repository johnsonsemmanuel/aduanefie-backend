<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CommunityZone;
use App\Models\DeliveryMan;
use App\Models\BusinessSetting;
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

        $communityZone = CommunityZone::where('zone_id', $request->zone_id)
            ->where('status', 1)
            ->first();

        if (!$communityZone) {
            return response()->json([
                'available' => false,
                'fee' => 0,
                'eta' => null,
                'agent_count' => 0,
            ], 200);
        }

        $agentCount = DeliveryMan::communityAgent()->active()->available()
            ->where('zone_id', $request->zone_id)
            ->count();

        $available = $agentCount > 0;
        $fee = (float) (BusinessSetting::where('key', 'community_delivery_fee')->first()?->value ?? 5.00);
        $eta = $available ? ($agentCount > 3 ? '15-30 min' : '30-60 min') : null;

        return response()->json([
            'available' => $available,
            'fee' => $fee,
            'eta' => $eta,
            'agent_count' => $agentCount,
        ], 200);
    }
}
