<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProcessingFacility;
use App\Models\ProcessingOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class ProcessingFacilityController extends Controller
{
    public function index(Request $request)
    {
        $query = ProcessingFacility::query();

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->has('facility_type')) {
            $query->where('facility_type', $request->facility_type);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $facilities = $query->with('store')->latest()->paginate(20);

        return response()->json([
            'facilities' => $facilities,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'facility_name' => 'required|string|max:255',
            'facility_type' => 'required|in:milling,oil_extraction,drying,cold_storage,packaging',
            'capacity_per_day' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $facility = new ProcessingFacility();
        $facility->store_id = $request->store_id;
        $facility->facility_name = $request->facility_name;
        $facility->facility_type = $request->facility_type;
        $facility->capacity_per_day = $request->capacity_per_day ?? 0;
        $facility->capacity_unit = $request->capacity_unit;
        $facility->location = $request->location;
        $facility->contact_person = $request->contact_person;
        $facility->contact_phone = $request->contact_phone;
        $facility->status = 'active';
        $facility->save();

        return response()->json([
            'message' => 'Processing facility created successfully',
            'facility' => $facility,
        ], 201);
    }

    public function show($id)
    {
        $facility = ProcessingFacility::with('store')->find($id);

        if (!$facility) {
            return response()->json(['message' => 'Processing facility not found'], 404);
        }

        return response()->json(['facility' => $facility], 200);
    }

    public function update(Request $request, $id)
    {
        $facility = ProcessingFacility::find($id);

        if (!$facility) {
            return response()->json(['message' => 'Processing facility not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'facility_name' => 'sometimes|string|max:255',
            'facility_type' => 'sometimes|in:milling,oil_extraction,drying,cold_storage,packaging',
            'capacity_per_day' => 'sometimes|numeric|min:0',
            'capacity_unit' => 'sometimes|string|max:50',
            'location' => 'sometimes|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'contact_phone' => 'sometimes|string|max:50',
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('facility_name')) $facility->facility_name = $request->facility_name;
        if ($request->has('facility_type')) $facility->facility_type = $request->facility_type;
        if ($request->has('capacity_per_day')) $facility->capacity_per_day = $request->capacity_per_day;
        if ($request->has('capacity_unit')) $facility->capacity_unit = $request->capacity_unit;
        if ($request->has('location')) $facility->location = $request->location;
        if ($request->has('contact_person')) $facility->contact_person = $request->contact_person;
        if ($request->has('contact_phone')) $facility->contact_phone = $request->contact_phone;
        if ($request->has('status')) $facility->status = $request->status;

        $facility->save();

        return response()->json([
            'message' => 'Processing facility updated successfully',
            'facility' => $facility,
        ], 200);
    }

    public function destroy($id)
    {
        $facility = ProcessingFacility::find($id);

        if (!$facility) {
            return response()->json(['message' => 'Processing facility not found'], 404);
        }

        $facility->delete();

        return response()->json(['message' => 'Processing facility deleted successfully'], 200);
    }
}
