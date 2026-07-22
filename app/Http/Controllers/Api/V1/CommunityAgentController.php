<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Marketer;
use App\Models\MarketerEarning;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityAgentController extends Controller
{
    public function earnings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|integer|exists:marketers,id',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);

        $query = MarketerEarning::where('marketer_id', $request->agent_id)
            ->where('status', 1)
            ->latest();

        $totalSize = $query->count();
        $earnings = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'earnings' => $earnings,
            'total_size' => $totalSize,
        ], 200);
    }
}
