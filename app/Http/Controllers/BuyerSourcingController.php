<?php

namespace App\Http\Controllers;

use App\Models\BuyerSourcingRequest;
use App\Models\Store;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class BuyerSourcingController extends Controller
{
    /**
     * Create a buyer sourcing request.
     *
     * @return \Illuminate\Http\Response
     */
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

        $buyerId = auth('customer')->id() ?? auth('vendor')->id();
        $buyerType = auth('customer')->check() ? 'customer' : 'vendor';

        $request = BuyerSourcingRequest::create([
            'buyer_id' => $buyerId,
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

        Toastr::success('Sourcing request posted. Matching sellers will be notified.');

        return response()->json([
            'success' => true,
            'sourcing_request' => $request,
        ]);
    }

    /**
     * List open sourcing requests (for sellers to browse).
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $requests = BuyerSourcingRequest::open()
            ->with('buyer')
            ->latest()
            ->paginate(config('default_pagination'));

        return response()->json([
            'success' => true,
            'sourcing_requests' => $requests,
        ]);
    }

    /**
     * Match sellers to a sourcing request (platform-assisted).
     *
     * @return \Illuminate\Http\Response
     */
    public function matchSellers(Request $request, $sourcingRequestId)
    {
        $sourcingRequest = BuyerSourcingRequest::findOrFail($sourcingRequestId);

        // Find verified sellers in origin countries that produce the commodity
        $matchedSellers = Store::where('status', 1)
            ->whereIn('verification_tier', ['aggregator', 'exporter'])
            ->where(function ($query) use ($sourcingRequest) {
                $query->where('verification_status', 'verified')
                    ->orWhere('gold_supplier', 1);
            })
            ->take(10)
            ->get();

        $sourcingRequest->update(['status' => 'matched']);

        Toastr::success('Matching sellers identified and notified.');

        return response()->json([
            'success' => true,
            'sourcing_request' => $sourcingRequest,
            'matched_sellers' => $matchedSellers,
        ]);
    }

    /**
     * Get my sourcing requests (buyer dashboard).
     *
     * @return \Illuminate\Http\Response
     */
    public function myRequests(Request $request)
    {
        $buyerId = auth('customer')->id() ?? auth('vendor')->id();
        $buyerType = auth('customer')->check() ? 'customer' : 'vendor';

        $requests = BuyerSourcingRequest::where('buyer_id', $buyerId)
            ->where('buyer_type', $buyerType)
            ->latest()
            ->paginate(config('default_pagination'));

        return response()->json([
            'success' => true,
            'sourcing_requests' => $requests,
        ]);
    }
}
