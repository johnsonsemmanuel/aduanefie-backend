<?php

namespace App\Http\Controllers;

use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use App\Models\Store;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class SellerDashboardController extends Controller
{
    /**
     * Show seller dashboard with received RFQs and active contracts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $vendorId = auth('vendor')->id();

        $store = Store::where('vendor_id', $vendorId)->first();

        // Received RFQs for this seller's products
        $receivedRfqs = TradeRequest::whereHas('product', function ($query) use ($vendorId) {
            $query->where('store_id', $store?->id ?? $vendorId);
        })
        ->where('status', 'open')
        ->with(['product'])
        ->latest()
        ->take(10)
        ->get();

        // Active quotes from this seller
        $activeQuotes = TradeRequestQuote::where('seller_id', $vendorId)
        ->where('status', 'pending')
        ->with(['tradeRequest.product'])
        ->latest()
        ->take(10)
        ->get();

        // Active contracts (seller side)
        $activeContracts = \App\Models\TradeContract::where('seller_id', $vendorId)
        ->whereIn('payment_status', ['deposited', 'released'])
        ->with(['tradeRequest.product', 'tradeRequest.buyer'])
        ->latest()
        ->take(10)
        ->get();

        $isGoldSupplier = $store?->isGoldSupplierActive() ?? false;
        $verificationBadge = $store?->getVerificationBadge() ?? null;
        $goldBadge = $store?->getGoldSupplierBadge() ?? null;

        return view('seller-dashboard.index', compact(
            'store', 'receivedRfqs', 'activeQuotes', 'activeContracts',
            'isGoldSupplier', 'verificationBadge', 'goldBadge'
        ));
    }

    /**
     * Respond to an RFQ with a quote.
     *
     * @return \Illuminate\Http\Response
     */
    public function respondToRfq(Request $request, $rfqId)
    {
        $rfq = TradeRequest::findOrFail($rfqId);

        $vendorId = auth('vendor')->id();
        $store = Store::where('vendor_id', $vendorId)->first();

        // Verify seller owns the product
        if ($rfq->product->store_id != $store->id) {
            return back()->with('error', 'Unauthorized');
        }

        $validator = Validator::make($request->all(), [
            'quoted_price' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'estimated_delivery' => 'nullable|date',
            'terms' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Check if already quoted
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

        Toastr::success('Quote submitted successfully.');

        return back();
    }

    /**
     * Upgrade to Gold Supplier.
     *
     * @return \Illuminate\Http\Response
     */
    public function upgradeGoldSupplier(Request $request)
    {
        $vendorId = auth('vendor')->id();
        $store = Store::where('vendor_id', $vendorId)->first();

        if (!$store) {
            return back()->with('error', 'Store not found');
        }

        $request->validate([
            'duration_months' => 'required|integer|min:1|max:12',
        ]);

        $store->update([
            'gold_supplier' => 1,
            'gold_supplier_expires_at' => now()->addMonths($request->duration_months),
        ]);

        Toastr::success('Gold Supplier status activated!');

        return back();
    }
}
