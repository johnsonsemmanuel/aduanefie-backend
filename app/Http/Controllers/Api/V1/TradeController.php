<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradeController extends Controller
{
    public function blocs()
    {
        if (Schema::hasTable('trade_blocs')) {
            $rows = DB::table('trade_blocs')->where('status', 'active')->get();
            if ($rows->isNotEmpty()) {
                return response()->json(['blocs' => $rows], 200);
            }
        }

        return response()->json([
            'blocs' => [
                [
                    'id' => 'ecowas',
                    'slug' => 'ecowas',
                    'name' => 'Economic Community of West African States (ECOWAS)',
                    'shortName' => 'ECOWAS',
                    'status' => 'active',
                ],
                [
                    'id' => 'afcfta',
                    'slug' => 'afcfta',
                    'name' => 'African Continental Free Trade Area (AfCFTA)',
                    'shortName' => 'AfCFTA',
                    'status' => 'active',
                ],
                [
                    'id' => 'eac',
                    'slug' => 'eac',
                    'name' => 'East African Community (EAC)',
                    'shortName' => 'EAC',
                    'status' => 'active',
                ],
                [
                    'id' => 'sadc',
                    'slug' => 'sadc',
                    'name' => 'Southern African Development Community (SADC)',
                    'shortName' => 'SADC',
                    'status' => 'active',
                ],
            ],
        ], 200);
    }

    public function corridors(Request $request)
    {
        $corridors = Schema::hasTable('trade_corridors')
            ? DB::table('trade_corridors')->get()
            : [];

        return response()->json(['corridors' => $corridors], 200);
    }

    public function customs_duties_calculate(Request $request)
    {
        $value = max(0, (float) ($request->input('value') ?? $request->input('order_amount') ?? 0));
        $duty_rate = 0.0; // AfCFTA preferential agricultural duty
        $vat_rate = 0.0;
        return response()->json([
            'goods_value' => $value,
            'duty_rate' => $duty_rate,
            'duty_amount' => round($value * $duty_rate, 2),
            'vat_amount' => round($value * $vat_rate, 2),
            'total_landed_estimate' => $value,
            'regime' => 'AfCFTA / ECOWAS ETLS',
        ], 200);
    }

    public function sourcing(Request $request)
    {
        return response()->json(['requests' => [], 'total' => 0], 200);
    }

    public function requests(Request $request)
    {
        return response()->json(['requests' => [], 'total' => 0], 200);
    }
}
