<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerSourcingRequest;
use App\Models\Store;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class BuyerSourcingController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'commodity' => 'required|string|max:100',
            'min_quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:20',
            'destination_country' => 'required|string|max:100',
            'incoterm' => 'required|in:EXW,FOB,CIF,DAP',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'needed_by' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $user = $request->user();
        $buyerType = $user instanceof \App\Models\User ? 'customer' : 'vendor';

        $sourcingRequest = BuyerSourcingRequest::create([
            'buyer_id' => $user->id,
            'buyer_type' => $buyerType,
            'title' => $request->title,
            'description' => $request->description,
            'commodity' => $request->commodity,
            'min_quantity' => $request->min_quantity,
            'unit' => $request->unit,
            'destination_country' => $request->destination_country,
            'incoterm' => $request->incoterm,
            'budget_min' => $request->budget_min,
            'budget_max' => $request->budget_max,
            'currency_code' => $request->currency_code,
            'needed_by' => $request->needed_by,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'sourcing_request' => $sourcingRequest,
        ], 201);
    }

    public function index(Request $request)
    {
        $requests = BuyerSourcingRequest::open()
            ->with('buyer')
            ->latest()
            ->paginate(config('default_pagination', 15));

        return response()->json([
            'success' => true,
            'sourcing_requests' => $requests,
        ], 200);
    }

    public function matchSellers(Request $request, $sourcingRequestId)
    {
        $sourcingRequest = BuyerSourcingRequest::findOrFail($sourcingRequestId);

        $matchedSellers = Store::where('status', 1)
            ->whereIn('verification_tier', ['aggregator', 'exporter'])
            ->where(function ($query) use ($sourcingRequest) {
                $query->where('verification_status', 'verified')
                    ->orWhere('gold_supplier', 1);
            })
            ->take(10)
            ->get();

        $sourcingRequest->update(['status' => 'matched']);

        return response()->json([
            'success' => true,
            'sourcing_request' => $sourcingRequest,
            'matched_sellers' => $matchedSellers,
        ], 200);
    }

    public function myRequests(Request $request)
    {
        $user = $request->user();
        $buyerType = $user instanceof \App\Models\User ? 'customer' : 'vendor';

        $requests = BuyerSourcingRequest::where('buyer_id', $user->id)
            ->where('buyer_type', $buyerType)
            ->latest()
            ->paginate(config('default_pagination', 15));

        return response()->json([
            'success' => true,
            'sourcing_requests' => $requests,
        ], 200);
    }
}
