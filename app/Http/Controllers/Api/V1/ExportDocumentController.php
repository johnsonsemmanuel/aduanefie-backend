<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExportDocument;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class ExportDocumentController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $documents = ExportDocument::where('order_id', $request->order_id)->get();

        return response()->json([
            'documents' => $documents,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'document_type' => 'required|in:phytosanitary,origin,health,certificate_of_origin,inspection,other',
            'document_number' => 'nullable|string|max:100',
            'issued_by' => 'required|string|max:255',
            'issued_at' => 'required|date',
            'valid_until' => 'required|date',
            'destination_country' => 'required|string|max:100',
            'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $order = Order::find($request->order_id);

        $document = new ExportDocument();
        $document->order_id = $request->order_id;
        $document->document_type = $request->document_type;
        $document->document_number = $request->document_number;
        $document->issued_by = $request->issued_by;
        $document->issued_at = $request->issued_at;
        $document->valid_until = $request->valid_until;
        $document->destination_country = $request->destination_country;
        $document->status = 'pending';

        if ($request->hasFile('certificate_file')) {
            $document->certificate_file = Helpers::upload('export_documents/', 'pdf', $request->file('certificate_file'));
        }

        $document->save();

        return response()->json([
            'message' => 'Export document created successfully',
            'document' => $document,
        ], 201);
    }

    public function show($id)
    {
        $document = ExportDocument::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return response()->json(['document' => $document], 200);
    }

    public function update(Request $request, $id)
    {
        $document = ExportDocument::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type' => 'sometimes|in:phytosanitary,origin,health,certificate_of_origin,inspection,other',
            'document_number' => 'sometimes|string|max:100',
            'issued_by' => 'sometimes|string|max:255',
            'issued_at' => 'sometimes|date',
            'valid_until' => 'sometimes|date',
            'destination_country' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:pending,issued,expired,rejected',
            'certificate_file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('document_type')) $document->document_type = $request->document_type;
        if ($request->has('document_number')) $document->document_number = $request->document_number;
        if ($request->has('issued_by')) $document->issued_by = $request->issued_by;
        if ($request->has('issued_at')) $document->issued_at = $request->issued_at;
        if ($request->has('valid_until')) $document->valid_until = $request->valid_until;
        if ($request->has('destination_country')) $document->destination_country = $request->destination_country;
        if ($request->has('status')) $document->status = $request->status;
        if ($request->hasFile('certificate_file')) {
            $document->certificate_file = Helpers::upload('export_documents/', 'pdf', $request->file('certificate_file'));
        }

        $document->save();

        return response()->json([
            'message' => 'Export document updated successfully',
            'document' => $document,
        ], 200);
    }

    public function destroy($id)
    {
        $document = ExportDocument::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $document->delete();

        return response()->json(['message' => 'Export document deleted successfully'], 200);
    }
}
