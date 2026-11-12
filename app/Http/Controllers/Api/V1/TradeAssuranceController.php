<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeAssurance;
use App\Models\TradeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class TradeAssuranceController extends Controller
{
    public function request(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || ($contract->buyer_id != $user->id && $contract->seller_id != $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($contract->assurance) {
            return response()->json(['error' => 'Assurance already exists for this contract.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'assurance_type' => 'required|in:quality,quantity,both',
            'inspection_fee' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $assurance = TradeAssurance::create([
            'trade_contract_id' => $contractId,
            'assurance_type' => $request->assurance_type,
            'status' => 'pending',
            'inspection_fee' => $request->inspection_fee,
            'currency_code' => $request->currency_code,
        ]);

        return response()->json(['success' => true, 'assurance' => $assurance], 200);
    }

    public function recordInspection(Request $request, $assuranceId)
    {
        $assurance = TradeAssurance::findOrFail($assuranceId);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:passed,failed',
            'inspector_name' => 'required|string|max:255',
            'inspector_organization' => 'required|string|max:255',
            'inspection_report' => 'required|string',
            'findings' => 'nullable|string',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_expiry' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $assurance->update([
            'status' => $request->status,
            'inspector_name' => $request->inspector_name,
            'inspector_organization' => $request->inspector_organization,
            'inspection_date' => now(),
            'inspection_report' => $request->inspection_report,
            'findings' => $request->findings,
            'certificate_number' => $request->certificate_number,
            'certificate_expiry' => $request->certificate_expiry,
        ]);

        return response()->json(['success' => true, 'assurance' => $assurance], 200);
    }

    public function status($contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || ($contract->buyer_id != $user->id && $contract->seller_id != $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $assurance = $contract->assurance;

        return response()->json([
            'success' => true,
            'assurance' => $assurance,
            'can_release_with_assurance' => $assurance ? $assurance->canReleaseWithAssurance() : false,
        ], 200);
    }
}
