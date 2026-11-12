<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeCorridor;
use App\Models\TradeBloc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class TradeCorridorController extends Controller
{
    public function index(Request $request)
    {
        $query = TradeCorridor::where('status', 'active');

        if ($request->origin) {
            $query->where('origin_country', $request->origin);
        }
        if ($request->destination) {
            $query->where('destination_country', $request->destination);
        }

        $corridors = $query->latest()->paginate(config('default_pagination', 15));

        return response()->json([
            'corridors' => $corridors,
        ], 200);
    }

    public function show(Request $request, $origin, $destination)
    {
        $corridor = TradeCorridor::where('origin_country', $origin)
            ->where('destination_country', $destination)
            ->where('status', 'active')
            ->first();

        if (!$corridor) {
            return response()->json(['error' => 'Corridor not found'], 404);
        }

        return response()->json(['corridor' => $corridor], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_country' => 'required|string|max:100',
            'destination_country' => 'required|string|max:100',
            'corridor_name' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric',
            'transit_days_estimate' => 'nullable|integer',
            'trucking_rate_per_ton_usd' => 'nullable|numeric',
            'minimum_charge_usd' => 'nullable|numeric',
            'cold_chain_available' => 'sometimes|boolean',
            'warehousing_available' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $corridor = TradeCorridor::create($validator->validated());

        return response()->json(['corridor' => $corridor], 201);
    }

    public function byBloc(Request $request, $slug)
    {
        $bloc = TradeBloc::where('slug', $slug)->where('status', 'active')->first();
        if (!$bloc) {
            return response()->json(['error' => 'Bloc not found'], 404);
        }

        $corridors = $bloc->corridors()->get();

        return response()->json([
            'bloc' => $bloc,
            'corridors' => $corridors,
        ], 200);
    }
}
