<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CropInsurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class CropInsuranceController extends Controller
{
    public function index(Request $request)
    {
        $query = CropInsurance::query();

        if ($request->has('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('crop_type')) {
            $query->where('crop_type', $request->crop_type);
        }

        $insurances = $query->with('farmer')->latest()->paginate(20);

        return response()->json([
            'insurances' => $insurances,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farmer_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'policy_number' => 'required|string|max:100|unique:crop_insurance',
            'crop_type' => 'required|string|max:100',
            'insured_value' => 'required|numeric|min:0',
            'premium_amount' => 'required|numeric|min:0',
            'coverage_type' => 'required|in:weather_indexed,yield_based,revenue',
            'risk_covered' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $insurance = new CropInsurance();
        $insurance->farmer_id = $request->farmer_id;
        $insurance->store_id = $request->store_id;
        $insurance->policy_number = $request->policy_number;
        $insurance->crop_type = $request->crop_type;
        $insurance->insured_value = $request->insured_value;
        $insurance->premium_amount = $request->premium_amount;
        $insurance->coverage_type = $request->coverage_type;
        $insurance->risk_covered = $request->risk_covered;
        $insurance->start_date = $request->start_date;
        $insurance->end_date = $request->end_date;
        $insurance->status = 'active';
        $insurance->save();

        return response()->json([
            'message' => 'Crop insurance policy created successfully',
            'insurance' => $insurance,
        ], 201);
    }

    public function show($id)
    {
        $insurance = CropInsurance::with('farmer')->find($id);

        if (!$insurance) {
            return response()->json(['message' => 'Insurance policy not found'], 404);
        }

        return response()->json(['insurance' => $insurance], 200);
    }

    public function update(Request $request, $id)
    {
        $insurance = CropInsurance::find($id);

        if (!$insurance) {
            return response()->json(['message' => 'Insurance policy not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:active,expired,claimed,cancelled',
            'crop_type' => 'sometimes|string|max:100',
            'insured_value' => 'sometimes|numeric|min:0',
            'premium_amount' => 'sometimes|numeric|min:0',
            'coverage_type' => 'sometimes|in:weather_indexed,yield_based,revenue',
            'risk_covered' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('status')) $insurance->status = $request->status;
        if ($request->has('crop_type')) $insurance->crop_type = $request->crop_type;
        if ($request->has('insured_value')) $insurance->insured_value = $request->insured_value;
        if ($request->has('premium_amount')) $insurance->premium_amount = $request->premium_amount;
        if ($request->has('coverage_type')) $insurance->coverage_type = $request->coverage_type;
        if ($request->has('risk_covered')) $insurance->risk_covered = $request->risk_covered;
        if ($request->has('start_date')) $insurance->start_date = $request->start_date;
        if ($request->has('end_date')) $insurance->end_date = $request->end_date;

        $insurance->save();

        return response()->json([
            'message' => 'Insurance policy updated successfully',
            'insurance' => $insurance,
        ], 200);
    }

    public function destroy($id)
    {
        $insurance = CropInsurance::find($id);

        if (!$insurance) {
            return response()->json(['message' => 'Insurance policy not found'], 404);
        }

        $insurance->delete();

        return response()->json(['message' => 'Insurance policy deleted successfully'], 200);
    }
}
