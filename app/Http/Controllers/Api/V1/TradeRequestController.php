<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeRequest;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class TradeRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userType = $user instanceof \App\Models\User ? 'customer' : 'vendor';
        $query = TradeRequest::where('buyer_id', $user->id)
            ->where('buyer_type', $userType)
            ->with(['product', 'quotes']);

        $requests = $query->latest()->paginate(config('default_pagination', 15));

        return response()->json(['trade_requests' => $requests], 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:20',
            'destination_country' => 'required|string|max:100',
            'incoterm' => 'required|in:EXW,FOB,CIF,DAP',
            'needed_by' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $product = Item::find($request->product_id);

        $tradeRequest = TradeRequest::create([
            'buyer_id' => $user->id,
            'buyer_type' => $user instanceof \App\Models\User ? 'customer' : 'vendor',
            'origin_country' => $product?->store?->zone?->name ?? null,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'destination_country' => $request->destination_country,
            'incoterm' => $request->incoterm,
            'needed_by' => $request->needed_by,
            'notes' => $request->notes,
            'status' => 'open',
        ]);

        $tradeRequest->load('product.store.zone');

        return response()->json([
            'trade_request' => $tradeRequest,
            'freight_estimate' => $tradeRequest->estimateFreight(),
            'duty_estimate' => $tradeRequest->estimateDuties(),
            'required_documents' => $tradeRequest->requiredDocuments(),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tradeRequest = TradeRequest::with(['product', 'quotes', 'documents'])->find($id);
        if (!$tradeRequest) {
            return response()->json(['error' => 'Trade request not found'], 404);
        }

        $userType = $user instanceof \App\Models\User ? 'customer' : 'vendor';
        if ($tradeRequest->buyer_id != $user->id && $tradeRequest->product->store_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['trade_request' => $tradeRequest], 200);
    }

    public function received(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tradeRequests = TradeRequest::whereHas('product', function ($query) use ($user) {
            $query->where('store_id', $user->id);
        })
        ->where('status', 'open')
        ->with(['buyer', 'product', 'quotes'])
        ->latest()
        ->paginate(config('default_pagination', 15));

        return response()->json(['trade_requests' => $tradeRequests], 200);
    }
}
