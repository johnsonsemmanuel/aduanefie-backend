<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomsDuty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class CustomsDutyController extends Controller
{
    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value_usd' => 'required|numeric|min:0',
            'hs_code' => 'required|string|max:20',
            'destination_country' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $duty = CustomsDuty::calculateDuties(
            $request->value_usd,
            $request->destination_country,
            $request->hs_code
        );

        return response()->json(['duty' => $duty], 200);
    }

    public function byCountry(Request $request, $country)
    {
        $duties = CustomsDuty::where('destination_country', $country)->get();

        return response()->json(['duties' => $duties], 200);
    }
}
