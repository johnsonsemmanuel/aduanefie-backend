<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProcessingOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class ProcessingOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = ProcessingOrder::query();

        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }
        if ($request->has('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->with(['facility', 'farmer'])->latest()->paginate(20);

        return response()->json([
            'processing_orders' => $orders,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'facility_id' => 'required|exists:processing_facilities,id',
            'farmer_id' => 'required|exists:users,id',
            'raw_material' => 'required|string|max:100',
            'raw_quantity' => 'required|numeric|min:0',
            'raw_unit' => 'required|string|max:50',
            'processed_product' => 'required|string|max:100',
            'processed_quantity' => 'nullable|numeric|min:0',
            'processed_unit' => 'nullable|string|max:50',
            'processing_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $order = new ProcessingOrder();
        $order->facility_id = $request->facility_id;
        $order->farmer_id = $request->farmer_id;
        $order->raw_material = $request->raw_material;
        $order->raw_quantity = $request->raw_quantity;
        $order->raw_unit = $request->raw_unit;
        $order->processed_product = $request->processed_product;
        $order->processed_quantity = $request->processed_quantity ?? 0;
        $order->processed_unit = $request->processed_unit;
        $order->processing_fee = $request->processing_fee ?? 0;
        $order->status = 'pending';
        $order->submitted_at = now();
        $order->save();

        return response()->json([
            'message' => 'Processing order created successfully',
            'processing_order' => $order,
        ], 201);
    }

    public function show($id)
    {
        $order = ProcessingOrder::with(['facility', 'farmer'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Processing order not found'], 404);
        }

        return response()->json(['processing_order' => $order], 200);
    }

    public function update(Request $request, $id)
    {
        $order = ProcessingOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Processing order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'raw_quantity' => 'sometimes|numeric|min:0',
            'processed_quantity' => 'sometimes|numeric|min:0',
            'processing_fee' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('status')) $order->status = $request->status;
        if ($request->has('raw_quantity')) $order->raw_quantity = $request->raw_quantity;
        if ($request->has('processed_quantity')) $order->processed_quantity = $request->processed_quantity;
        if ($request->has('processing_fee')) $order->processing_fee = $request->processing_fee;

        if ($request->status == 'in_progress') {
            $order->started_at = now();
        }
        if ($request->status == 'completed') {
            $order->completed_at = now();
        }

        $order->save();

        return response()->json([
            'message' => 'Processing order updated successfully',
            'processing_order' => $order,
        ], 200);
    }

    public function destroy($id)
    {
        $order = ProcessingOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Processing order not found'], 404);
        }

        $order->delete();

        return response()->json(['message' => 'Processing order deleted successfully'], 200);
    }
}
