<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgroCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class AgroCreditController extends Controller
{
    public function index(Request $request)
    {
        $query = AgroCredit::query();

        if ($request->has('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('credit_type')) {
            $query->where('credit_type', $request->credit_type);
        }

        $credits = $query->with('farmer')->latest()->paginate(20);

        return response()->json([
            'credits' => $credits,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farmer_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'credit_type' => 'required|in:input_loan,harvest_advance,equipment_loan',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1',
            'purpose' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $credit = new AgroCredit();
        $credit->farmer_id = $request->farmer_id;
        $credit->store_id = $request->store_id;
        $credit->credit_type = $request->credit_type;
        $credit->amount = $request->amount;
        $credit->interest_rate = $request->interest_rate ?? 0;
        $credit->term_months = $request->term_months ?? 0;
        $credit->purpose = $request->purpose;
        $credit->status = 'pending';
        $credit->applied_at = now();
        $credit->save();

        return response()->json([
            'message' => 'Credit application submitted successfully',
            'credit' => $credit,
        ], 201);
    }

    public function show($id)
    {
        $credit = AgroCredit::with('farmer')->find($id);

        if (!$credit) {
            return response()->json(['message' => 'Credit application not found'], 404);
        }

        return response()->json(['credit' => $credit], 200);
    }

    public function update(Request $request, $id)
    {
        $credit = AgroCredit::find($id);

        if (!$credit) {
            return response()->json(['message' => 'Credit application not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,approved,disbursed,repaid,defaulted',
            'amount' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0',
            'term_months' => 'sometimes|integer|min:1',
            'purpose' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('status')) $credit->status = $request->status;
        if ($request->has('amount')) $credit->amount = $request->amount;
        if ($request->has('interest_rate')) $credit->interest_rate = $request->interest_rate;
        if ($request->has('term_months')) $credit->term_months = $request->term_months;
        if ($request->has('purpose')) $credit->purpose = $request->purpose;

        if ($request->status == 'approved') {
            $credit->approved_at = now();
        }
        if ($request->status == 'disbursed') {
            $credit->disbursed_at = now();
        }
        if ($request->status == 'repaid') {
            $credit->repaid_at = now();
        }

        $credit->save();

        return response()->json([
            'message' => 'Credit updated successfully',
            'credit' => $credit,
        ], 200);
    }

    public function destroy($id)
    {
        $credit = AgroCredit::find($id);

        if (!$credit) {
            return response()->json(['message' => 'Credit application not found'], 404);
        }

        $credit->delete();

        return response()->json(['message' => 'Credit application deleted successfully'], 200);
    }
}
