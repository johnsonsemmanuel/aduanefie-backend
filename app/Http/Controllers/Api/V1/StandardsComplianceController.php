<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StandardsCompliance;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class StandardsComplianceController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $compliances = StandardsCompliance::where('item_id', $request->item_id)->get();

        return response()->json([
            'compliances' => $compliances,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'standard_name' => 'required|string|max:100',
            'certificate_number' => 'nullable|string|max:100',
            'issuing_body' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
            'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'sometimes|in:active,expired,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $item = Item::find($request->item_id);

        $compliance = new StandardsCompliance();
        $compliance->item_id = $request->item_id;
        $compliance->standard_name = $request->standard_name;
        $compliance->certificate_number = $request->certificate_number;
        $compliance->issuing_body = $request->issuing_body;
        $compliance->issue_date = $request->issue_date;
        $compliance->expiry_date = $request->expiry_date;
        $compliance->status = $request->status ?? 'active';

        if ($request->hasFile('certificate_file')) {
            $compliance->certificate_file = Helpers::upload('standards_certificates/', 'pdf', $request->file('certificate_file'));
        }

        $compliance->save();

        return response()->json([
            'message' => 'Standards compliance created successfully',
            'compliance' => $compliance,
        ], 201);
    }

    public function show($id)
    {
        $compliance = StandardsCompliance::find($id);

        if (!$compliance) {
            return response()->json(['message' => 'Compliance record not found'], 404);
        }

        return response()->json(['compliance' => $compliance], 200);
    }

    public function update(Request $request, $id)
    {
        $compliance = StandardsCompliance::find($id);

        if (!$compliance) {
            return response()->json(['message' => 'Compliance record not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'standard_name' => 'sometimes|string|max:100',
            'certificate_number' => 'sometimes|string|max:100',
            'issuing_body' => 'sometimes|string|max:255',
            'issue_date' => 'sometimes|date',
            'expiry_date' => 'sometimes|date|after:issue_date',
            'certificate_file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'sometimes|in:active,expired,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('standard_name')) $compliance->standard_name = $request->standard_name;
        if ($request->has('certificate_number')) $compliance->certificate_number = $request->certificate_number;
        if ($request->has('issuing_body')) $compliance->issuing_body = $request->issuing_body;
        if ($request->has('issue_date')) $compliance->issue_date = $request->issue_date;
        if ($request->has('expiry_date')) $compliance->expiry_date = $request->expiry_date;
        if ($request->has('status')) $compliance->status = $request->status;
        if ($request->hasFile('certificate_file')) {
            $compliance->certificate_file = Helpers::upload('standards_certificates/', 'pdf', $request->file('certificate_file'));
        }

        $compliance->save();

        return response()->json([
            'message' => 'Standards compliance updated successfully',
            'compliance' => $compliance,
        ], 200);
    }

    public function destroy($id)
    {
        $compliance = StandardsCompliance::find($id);

        if (!$compliance) {
            return response()->json(['message' => 'Compliance record not found'], 404);
        }

        $compliance->delete();

        return response()->json(['message' => 'Standards compliance deleted successfully'], 200);
    }
}
