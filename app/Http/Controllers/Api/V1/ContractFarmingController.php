<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContractFarming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class ContractFarmingController extends Controller
{
    public function index(Request $request)
    {
        $query = ContractFarming::query();

        if ($request->has('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }
        if ($request->has('buyer_id')) {
            $query->where('buyer_id', $request->buyer_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('crop_type')) {
            $query->where('crop_type', $request->crop_type);
        }

        $contracts = $query->with(['farmer', 'buyer'])->latest()->paginate(20);

        return response()->json([
            'contracts' => $contracts,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farmer_id' => 'required|exists:users,id',
            'buyer_id' => 'required|exists:users,id',
            'crop_type' => 'required|string|max:100',
            'acreage' => 'required|numeric|min:0',
            'expected_yield' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'agreed_price' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'planting_date' => 'required|date',
            'expected_harvest_date' => 'required|date|after:planting_date',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $contract = new ContractFarming();
        $contract->farmer_id = $request->farmer_id;
        $contract->buyer_id = $request->buyer_id;
        $contract->crop_type = $request->crop_type;
        $contract->acreage = $request->acreage;
        $contract->expected_yield = $request->expected_yield;
        $contract->unit = $request->unit;
        $contract->agreed_price = $request->agreed_price;
        $contract->currency_code = $request->currency_code ?? 'GHS';
        $contract->planting_date = $request->planting_date;
        $contract->expected_harvest_date = $request->expected_harvest_date;
        $contract->status = 'draft';

        if ($request->hasFile('contract_file')) {
            $contract->contract_file = Helpers::upload('contract_farming/', 'pdf', $request->file('contract_file'));
        }

        $contract->save();

        return response()->json([
            'message' => 'Contract farming agreement created successfully',
            'contract' => $contract,
        ], 201);
    }

    public function show($id)
    {
        $contract = ContractFarming::with(['farmer', 'buyer'])->find($id);

        if (!$contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        return response()->json(['contract' => $contract], 200);
    }

    public function update(Request $request, $id)
    {
        $contract = ContractFarming::find($id);

        if (!$contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:draft,signed,active,completed,cancelled',
            'crop_type' => 'sometimes|string|max:100',
            'acreage' => 'sometimes|numeric|min:0',
            'expected_yield' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'agreed_price' => 'sometimes|numeric|min:0',
            'currency_code' => 'sometimes|string|size:3',
            'planting_date' => 'sometimes|date',
            'expected_harvest_date' => 'sometimes|date|after:planting_date',
            'actual_harvest_date' => 'sometimes|date',
            'contract_file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('status')) $contract->status = $request->status;
        if ($request->has('crop_type')) $contract->crop_type = $request->crop_type;
        if ($request->has('acreage')) $contract->acreage = $request->acreage;
        if ($request->has('expected_yield')) $contract->expected_yield = $request->expected_yield;
        if ($request->has('unit')) $contract->unit = $request->unit;
        if ($request->has('agreed_price')) $contract->agreed_price = $request->agreed_price;
        if ($request->has('currency_code')) $contract->currency_code = $request->currency_code;
        if ($request->has('planting_date')) $contract->planting_date = $request->planting_date;
        if ($request->has('expected_harvest_date')) $contract->expected_harvest_date = $request->expected_harvest_date;
        if ($request->has('actual_harvest_date')) $contract->actual_harvest_date = $request->actual_harvest_date;
        if ($request->hasFile('contract_file')) {
            $contract->contract_file = Helpers::upload('contract_farming/', 'pdf', $request->file('contract_file'));
        }

        $contract->save();

        return response()->json([
            'message' => 'Contract farming agreement updated successfully',
            'contract' => $contract,
        ], 200);
    }

    public function destroy($id)
    {
        $contract = ContractFarming::find($id);

        if (!$contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        $contract->delete();

        return response()->json(['message' => 'Contract deleted successfully'], 200);
    }
}
