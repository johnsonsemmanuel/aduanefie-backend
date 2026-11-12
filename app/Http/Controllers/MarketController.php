<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class MarketController extends Controller
{
    /**
     * Show the customer-facing market listing page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Item::where('status', 1)
            ->whereIn('module_id', [20])
            ->with(['store', 'category']);

        // Filter by category
        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        // Search
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by origin country (from store zone)
        if ($request->origin) {
            $query->whereHas('store', function ($q) use ($request) {
                $q->where('zone_id', $request->origin);
            });
        }

        $commodities = $query->paginate(config('default_pagination'));
        $categories = Category::where('module_id', 20)->where('status', 1)->get();

        return view('market.index', compact('commodities', 'categories'));
    }

    /**
     * Show product detail page with RFQ form.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $product = Item::with(['store', 'category', 'bulkPricingTiers'])->findOrFail($id);

        $bulkPrice = $product->getBulkPrice(1);

        // Get related commodities from same category
        $related = Item::where('status', 1)
            ->where('module_id', 20)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->limit(4)
            ->get();

        return view('market.show', compact('product', 'bulkPrice', 'related'));
    }

    /**
     * Create a trade request (RFQ) from the market page.
     *
     * @return \Illuminate\Http\Response
     */
    public function requestQuote(Request $request)
    {
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
            return back()->withErrors($validator)->withInput();
        }

        $product = Item::find($request->product_id);

        $tradeRequest = TradeRequest::create([
            'buyer_id' => auth('customer')->id() ?? auth('vendor')->id(),
            'buyer_type' => auth('customer')->check() ? 'customer' : 'vendor',
            'origin_country' => $product->store->zone?->name ?? null,
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

        $freightEstimate = $tradeRequest->estimateFreight();
        $dutyEstimate = $tradeRequest->estimateDuties();

        Toastr::success('Trade request submitted successfully. Sellers will review and quote.');

        return redirect()->route('market.show', $product->id)
            ->with('trade_request', $tradeRequest)
            ->with('freight_estimate', $freightEstimate)
            ->with('duty_estimate', $dutyEstimate)
            ->with('required_documents', $tradeRequest->requiredDocuments());
    }

    /**
     * Show my trade requests (buyer dashboard).
     *
     * @return \Illuminate\Http\Response
     */
    public function myRequests(Request $request)
    {
        $buyerId = auth('customer')->id() ?? auth('vendor')->id();
        $buyerType = auth('customer')->check() ? 'customer' : 'vendor';

        $requests = TradeRequest::where('buyer_id', $buyerId)
            ->where('buyer_type', $buyerType)
            ->with(['product', 'quotes', 'contract'])
            ->latest()
            ->paginate(config('default_pagination'));

        return view('market.my-requests', compact('requests'));
    }

    /**
     * Show quotes received for a trade request.
     *
     * @return \Illuminate\Http\Response
     */
    public function quotes(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::with(['quotes.seller', 'product'])->findOrFail($tradeRequestId);

        $buyerId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $buyerId) {
            return back()->with('error', 'Unauthorized');
        }

        return view('market.quotes', compact('tradeRequest'));
    }

    /**
     * Accept a quote from the UI.
     *
     * @return \Illuminate\Http\Response
     */
    public function acceptQuote(Request $request, $quoteId)
    {
        $quote = TradeRequestQuote::findOrFail($quoteId);
        $tradeRequest = $quote->tradeRequest;

        $buyerId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $buyerId) {
            return back()->with('error', 'Unauthorized');
        }

        TradeRequestQuote::where('trade_request_id', $tradeRequest->id)
            ->where('id', '!=', $quote->id)
            ->update(['status' => 'rejected']);

        $quote->update(['status' => 'accepted']);
        $tradeRequest->update(['status' => 'accepted']);

        // Create trade contract
        $tradeRequest->createContractFromQuote();

        Toastr::success('Quote accepted. Trade contract created. Proceed to escrow deposit.');

        return redirect()->route('market.my-requests');
    }
}
