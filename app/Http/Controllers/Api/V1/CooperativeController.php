<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\CooperativeMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class CooperativeController extends Controller
{
    public function index(Request $request)
    {
        $query = Cooperative::query();

        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $cooperatives = $query->with('members')->latest()->paginate(20);

        return response()->json([
            'cooperatives' => $cooperatives,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'zone_id' => 'nullable|exists:zones,id',
            'contact_person' => 'required|string|max:255',
            'contact_phone' => 'required|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'share_capital' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $cooperative = new Cooperative();
        $cooperative->name = $request->name;
        $cooperative->registration_number = $request->registration_number;
        $cooperative->zone_id = $request->zone_id;
        $cooperative->contact_person = $request->contact_person;
        $cooperative->contact_phone = $request->contact_phone;
        $cooperative->contact_email = $request->contact_email;
        $cooperative->address = $request->address;
        $cooperative->members_count = 1;
        $cooperative->share_capital = $request->share_capital ?? 0;
        $cooperative->status = 'active';
        $cooperative->save();

        // Add the creator as the first member (chairman)
        CooperativeMember::create([
            'cooperative_id' => $cooperative->id,
            'farmer_id' => $request->user_id ?? $request->authenticated_user_id ?? 1,
            'join_date' => now(),
            'shares_owned' => 1,
            'role' => 'chairman',
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Cooperative created successfully',
            'cooperative' => $cooperative,
        ], 201);
    }

    public function show($id)
    {
        $cooperative = Cooperative::with('members')->find($id);

        if (!$cooperative) {
            return response()->json(['message' => 'Cooperative not found'], 404);
        }

        return response()->json(['cooperative' => $cooperative], 200);
    }

    public function update(Request $request, $id)
    {
        $cooperative = Cooperative::find($id);

        if (!$cooperative) {
            return response()->json(['message' => 'Cooperative not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:100',
            'zone_id' => 'sometimes|exists:zones,id',
            'contact_person' => 'sometimes|string|max:255',
            'contact_phone' => 'sometimes|string|max:50',
            'contact_email' => 'sometimes|email|max:255',
            'address' => 'sometimes|string',
            'share_capital' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,dissolved',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('name')) $cooperative->name = $request->name;
        if ($request->has('registration_number')) $cooperative->registration_number = $request->registration_number;
        if ($request->has('zone_id')) $cooperative->zone_id = $request->zone_id;
        if ($request->has('contact_person')) $cooperative->contact_person = $request->contact_person;
        if ($request->has('contact_phone')) $cooperative->contact_phone = $request->contact_phone;
        if ($request->has('contact_email')) $cooperative->contact_email = $request->contact_email;
        if ($request->has('address')) $cooperative->address = $request->address;
        if ($request->has('share_capital')) $cooperative->share_capital = $request->share_capital;
        if ($request->has('status')) $cooperative->status = $request->status;

        $cooperative->save();

        return response()->json([
            'message' => 'Cooperative updated successfully',
            'cooperative' => $cooperative,
        ], 200);
    }

    public function destroy($id)
    {
        $cooperative = Cooperative::find($id);

        if (!$cooperative) {
            return response()->json(['message' => 'Cooperative not found'], 404);
        }

        $cooperative->delete();

        return response()->json(['message' => 'Cooperative deleted successfully'], 200);
    }
}
