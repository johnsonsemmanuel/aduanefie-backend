<?php

namespace App\Http\Controllers;

use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use App\Models\TradeDocument;
use App\Models\Item;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;

class TradeRequestController extends Controller
{
    /**
     * Create a new trade request (RFQ).
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
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
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $product = Item::find($request->product_id);

        $tradeRequest = TradeRequest::create([
            'buyer_id' => auth('customer')->id() ?? auth('vendor')->id(),
            'buyer_type' => auth('customer')->check() ? 'customer' : 'vendor',
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

        // Compute cross-border estimates
        $freightEstimate = $tradeRequest->estimateFreight();
        $dutyEstimate = $tradeRequest->estimateDuties();

        Toastr::success('Trade request submitted successfully.');

        return response()->json([
            'success' => true,
            'trade_request' => $tradeRequest,
            'freight_estimate' => $freightEstimate,
            'duty_estimate' => $dutyEstimate,
            'logistics_summary' => $tradeRequest->logisticsSummary(),
            'cold_chain_available' => $tradeRequest->coldChainAvailable(),
            'warehousing_available' => $tradeRequest->warehousingAvailable(),
            'required_documents' => $tradeRequest->requiredDocuments(),
        ]);
    }

    /**
     * List trade requests for the authenticated user.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = auth('customer')->id() ?? auth('vendor')->id();
        $userType = auth('customer')->check() ? 'customer' : 'vendor';

        $tradeRequests = TradeRequest::where('buyer_id', $userId)
            ->where('buyer_type', $userType)
            ->with(['product', 'quotes'])
            ->latest()
            ->paginate(config('default_pagination'));

        return response()->json([
            'success' => true,
            'trade_requests' => $tradeRequests,
        ]);
    }

    /**
     * List trade requests received by a vendor (for their products).
     *
     * @return \Illuminate\Http\Response
     */
    public function received(Request $request)
    {
        $vendorId = auth('vendor')->id();

        $tradeRequests = TradeRequest::whereHas('product', function ($query) use ($vendorId) {
            $query->where('store_id', $vendorId);
        })
            ->with(['buyer', 'product', 'quotes'])
            ->where('status', 'open')
            ->latest()
            ->paginate(config('default_pagination'));

        return response()->json([
            'success' => true,
            'trade_requests' => $tradeRequests,
        ]);
    }

    /**
     * Respond to a trade request with a quote.
     *
     * @return \Illuminate\Http\Response
     */
    public function quote(Request $request, $tradeRequestId)
    {
        $validator = Validator::make($request->all(), [
            'quoted_price' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'estimated_delivery' => 'nullable|date',
            'terms' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        // Load product for estimates
        $tradeRequest->load('product');

        $quote = TradeRequestQuote::create([
            'trade_request_id' => $tradeRequestId,
            'seller_id' => auth('vendor')->id(),
            'seller_type' => 'vendor',
            'quoted_price' => $request->quoted_price,
            'currency_code' => $request->currency_code,
            'estimated_delivery' => $request->estimated_delivery,
            'terms' => $request->terms,
            'status' => 'pending',
        ]);

        $tradeRequest->update(['status' => 'quoted']);

        // Compute cross-border estimates for the quote response
        $freightEstimate = $tradeRequest->estimateFreight();
        $dutyEstimate = $tradeRequest->estimateDuties();

        Toastr::success('Quote submitted successfully.');

        return response()->json([
            'success' => true,
            'quote' => $quote->load('tradeRequest'),
            'freight_estimate' => $freightEstimate,
            'duty_estimate' => $dutyEstimate,
            'logistics_summary' => $tradeRequest->logisticsSummary(),
            'cold_chain_available' => $tradeRequest->coldChainAvailable(),
            'warehousing_available' => $tradeRequest->warehousingAvailable(),
            'required_documents' => $tradeRequest->requiredDocuments(),
        ]);
    }

    /**
     * Accept a quote.
     *
     * @return \Illuminate\Http\Response
     */
    public function acceptQuote(Request $request, $quoteId)
    {
        $quote = TradeRequestQuote::findOrFail($quoteId);
        $tradeRequest = $quote->tradeRequest;

        // Verify buyer owns this trade request
        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Reject all other quotes
        TradeRequestQuote::where('trade_request_id', $tradeRequest->id)
            ->where('id', '!=', $quote->id)
            ->update(['status' => 'rejected']);

        $quote->update(['status' => 'accepted']);
        $tradeRequest->update(['status' => 'accepted']);

        Toastr::success('Quote accepted. Trade agreement created.');

        return response()->json([
            'success' => true,
            'trade_request' => $tradeRequest,
            'quote' => $quote,
        ]);
    }

    /**
     * Reject a quote.
     *
     * @return \Illuminate\Http\Response
     */
    public function rejectQuote(Request $request, $quoteId)
    {
        $quote = TradeRequestQuote::findOrFail($quoteId);
        $tradeRequest = $quote->tradeRequest;

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $quote->update(['status' => 'rejected']);

        Toastr::success('Quote rejected.');

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Upload a trade document (SPS, phytosanitary, origin cert, etc.).
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trade_request_id' => 'required|exists:trade_requests,id',
            'document_type' => 'required|in:sps_certificate,phytosanitary,origin_certificate,export_permit,other',
            'file' => 'required|file|max:10240',
            'issued_by' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $tradeRequest = TradeRequest::find($request->trade_request_id);

        // Verify buyer or seller owns this trade request
        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $userId && $tradeRequest->product->store_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $fileName = Helpers::upload('trade_documents/', 'pdf', $request->file('file'));

        $document = TradeDocument::create([
            'trade_request_id' => $request->trade_request_id,
            'document_type' => $request->document_type,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $fileName,
            'storage' => Helpers::getDisk(),
            'issued_by' => $request->issued_by,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'status' => 'pending',
        ]);

        Toastr::success('Document uploaded successfully.');

        return response()->json([
            'success' => true,
            'document' => $document,
        ]);
    }

    /**
     * List documents for a trade request.
     *
     * @return \Illuminate\Http\Response
     */
    public function documents(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $userId && $tradeRequest->product->store_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $documents = TradeDocument::where('trade_request_id', $tradeRequestId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'documents' => $documents,
        ]);
    }
}
