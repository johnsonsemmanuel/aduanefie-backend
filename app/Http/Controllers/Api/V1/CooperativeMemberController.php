<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CooperativeMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class CooperativeMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = CooperativeMember::query();

        if ($request->has('cooperative_id')) {
            $query->where('cooperative_id', $request->cooperative_id);
        }
        if ($request->has('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $members = $query->with(['cooperative', 'farmer'])->latest()->paginate(20);

        return response()->json([
            'members' => $members,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cooperative_id' => 'required|exists:cooperatives,id',
            'farmer_id' => 'required|exists:users,id',
            'join_date' => 'nullable|date',
            'shares_owned' => 'nullable|integer|min:1',
            'role' => 'nullable|in:member,chairman,treasurer,secretary',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $member = new CooperativeMember();
        $member->cooperative_id = $request->cooperative_id;
        $member->farmer_id = $request->farmer_id;
        $member->join_date = $request->join_date ?? now();
        $member->shares_owned = $request->shares_owned ?? 1;
        $member->role = $request->role ?? 'member';
        $member->status = 'active';
        $member->save();

        // Update cooperative member count
        $cooperative = \App\Models\Cooperative::find($request->cooperative_id);
        $cooperative->members_count = CooperativeMember::where('cooperative_id', $request->cooperative_id)->where('status', 'active')->count();
        $cooperative->save();

        return response()->json([
            'message' => 'Member added to cooperative successfully',
            'member' => $member,
        ], 201);
    }

    public function show($id)
    {
        $member = CooperativeMember::with(['cooperative', 'farmer'])->find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        return response()->json(['member' => $member], 200);
    }

    public function update(Request $request, $id)
    {
        $member = CooperativeMember::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'shares_owned' => 'sometimes|integer|min:1',
            'role' => 'sometimes|in:member,chairman,treasurer,secretary',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if ($request->has('shares_owned')) $member->shares_owned = $request->shares_owned;
        if ($request->has('role')) $member->role = $request->role;
        if ($request->has('status')) $member->status = $request->status;

        $member->save();

        // Update cooperative member count
        $cooperative = \App\Models\Cooperative::find($member->cooperative_id);
        $cooperative->members_count = CooperativeMember::where('cooperative_id', $member->cooperative_id)->where('status', 'active')->count();
        $cooperative->save();

        return response()->json([
            'message' => 'Member updated successfully',
            'member' => $member,
        ], 200);
    }

    public function destroy($id)
    {
        $member = CooperativeMember::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $cooperativeId = $member->cooperative_id;
        $member->delete();

        // Update cooperative member count
        $cooperative = \App\Models\Cooperative::find($cooperativeId);
        $cooperative->members_count = CooperativeMember::where('cooperative_id', $cooperativeId)->where('status', 'active')->count();
        $cooperative->save();

        return response()->json(['message' => 'Member removed from cooperative successfully'], 200);
    }
}
