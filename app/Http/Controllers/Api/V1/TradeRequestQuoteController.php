<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;

class TradeRequestQuoteController extends Controller
{
    public function store(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'quoted_price' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'estimated_delivery' => 'nullable|date',
            'terms' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $quote = TradeRequestQuote::create([
            'trade_request_id' => $tradeRequestId,
            'seller_id' => $user->id,
            'seller_type' => 'vendor',
            'quoted_price' => $request->quoted_price,
            'currency_code' => $request->currency_code,
            'estimated_delivery' => $request->estimated_delivery,
            'terms' => $request->terms,
            'status' => 'pending',
        ]);

        $tradeRequest->update(['status' => 'quoted']);

        return response()->json([
            'success' => true,
            'quote' => $quote->load('tradeRequest'),
        ], 201);
    }

    public function index(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);
        $quotes = TradeRequestQuote::where('trade_request_id', $tradeRequestId)->latest()->get();

        return response()->json(['quotes' => $quotes], 200);
    }

    public function accept(Request $request, $quoteId)
    {
        $quote = TradeRequestQuote::findOrFail($quoteId);
        $tradeRequest = $quote->tradeRequest;

        $user = $request->user();
        if (!$user || $tradeRequest->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        TradeRequestQuote::where('trade_request_id', $tradeRequest->id)
            ->where('id', '!=', $quote->id)
            ->update(['status' => 'rejected']);

        $quote->update(['status' => 'accepted']);
        $tradeRequest->update(['status' => 'accepted']);
        $tradeRequest->createContractFromQuote();

        return response()->json([
            'success' => true,
            'trade_request' => $tradeRequest,
            'quote' => $quote,
        ], 200);
    }

    public function reject(Request $request, $quoteId)
    {
        $quote = TradeRequestQuote::findOrFail($quoteId);
        $tradeRequest = $quote->tradeRequest;

        $user = $request->user();
        if (!$user || $tradeRequest->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $quote->update(['status' => 'rejected']);

        return response()->json(['success' => true], 200);
    }
}
