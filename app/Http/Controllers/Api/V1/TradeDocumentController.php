<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeDocument;
use App\Models\TradeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class TradeDocumentController extends Controller
{
    public function upload(Request $request)
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

        $user = $request->user();
        if (!$user || ($tradeRequest->buyer_id != $user->id && $tradeRequest->product->store_id != $user->id)) {
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

        return response()->json(['success' => true, 'document' => $document], 200);
    }

    public function index(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        $user = $request->user();
        if (!$user || ($tradeRequest->buyer_id != $user->id && $tradeRequest->product->store_id != $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $documents = TradeDocument::where('trade_request_id', $tradeRequestId)->latest()->get();

        return response()->json(['success' => true, 'documents' => $documents], 200);
    }
}
