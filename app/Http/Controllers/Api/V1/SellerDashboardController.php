<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use App\Models\Store;
use App\Models\TradeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class SellerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $vendorId = $user->id;

        $store = Store::where('vendor_id', $vendorId)->first();

        $storeId = $store ? $store->id : $vendorId;

        $receivedRfqs = TradeRequest::whereHas('product', function ($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })
        ->where('status', 'open')
        ->with(['product'])
        ->latest()
        ->take(10)
        ->get();

        $activeQuotes = TradeRequestQuote::where('seller_id', $vendorId)
        ->where('status', 'pending')
        ->with(['tradeRequest.product'])
        ->latest()
        ->take(10)
        ->get();

        $activeContracts = TradeContract::where('seller_id', $vendorId)
        ->whereIn('payment_status', ['deposited', 'released'])
        ->with(['tradeRequest.product', 'tradeRequest.buyer'])
        ->latest()
        ->take(10)
        ->get();

        $isGoldSupplier = $store?->isGoldSupplierActive() ?? false;
        $verificationBadge = $store?->getVerificationBadge() ?? null;
        $goldBadge = $store?->getGoldSupplierBadge() ?? null;

        return response()->json([
            'store' => $store,
            'received_rfqs' => $receivedRfqs,
            'active_quotes' => $activeQuotes,
            'active_contracts' => $activeContracts,
            'is_gold_supplier' => $isGoldSupplier,
            'verification_badge' => $verificationBadge,
            'gold_badge' => $goldBadge,
        ], 200);
    }

    public function respondToRfq(Request $request, $rfqId)
    {
        $rfq = TradeRequest::findOrFail($rfqId);

        $user = $request->user();
        $vendorId = $user->id;
        $store = Store::where('vendor_id', $vendorId)->first();

        if (!$store || $rfq->product->store_id != $store->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
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

        $existingQuote = TradeRequestQuote::where('trade_request_id', $rfqId)
            ->where('seller_id', $vendorId)
            ->first();

        if ($existingQuote) {
            $existingQuote->update([
                'quoted_price' => $request->quoted_price,
                'currency_code' => $request->currency_code,
                'estimated_delivery' => $request->estimated_delivery,
                'terms' => $request->terms,
            ]);
        } else {
            TradeRequestQuote::create([
                'trade_request_id' => $rfqId,
                'seller_id' => $vendorId,
                'seller_type' => 'vendor',
                'quoted_price' => $request->quoted_price,
                'currency_code' => $request->currency_code,
                'estimated_delivery' => $request->estimated_delivery,
                'terms' => $request->terms,
                'status' => 'pending',
            ]);
        }

        $rfq->update(['status' => 'quoted']);

        return response()->json([
            'success' => true,
            'message' => 'Quote submitted successfully.',
        ], 200);
    }

    public function upgradeGoldSupplier(Request $request)
    {
        $user = $request->user();
        $vendorId = $user->id;
        $store = Store::where('vendor_id', $vendorId)->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $request->validate([
            'duration_months' => 'required|integer|min:1|max:12',
        ]);

        $store->update([
            'gold_supplier' => 1,
            'gold_supplier_expires_at' => now()->addMonths($request->duration_months),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gold Supplier status activated!',
        ], 200);
    }
}
