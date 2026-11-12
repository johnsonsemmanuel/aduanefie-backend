<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class ModuleController extends Controller
{

    public function index(Request $request)
    {
        $zoneKey = $request->hasHeader('zoneId')
            ? 'h_' . md5((string) $request->header('zoneId'))
            : 'q_' . (string) $request->zone_id;

        $modules = Cache::remember('api_v1_modules_' . $zoneKey, now()->addMinutes(2), function () use ($request) {
            if ($request->hasHeader('zoneId')) {
                $zone_id = json_decode($request->header('zoneId'), true);

                $zone_id = is_array($zone_id) ? $zone_id : [$zone_id];

                $modules = Module::with('zones')
                    ->withCount([
                        'items',
                        'stores' => function ($query) use ($zone_id) {
                            $query->whereIn('zone_id', $zone_id);
                        }
                    ])
                    ->whereHas('zones', function ($query) use ($zone_id) {
                        $query->whereIn('zone_id', $zone_id);
                    })
                    ->active()
                    ->get();
            } else {
                $modules = Module::withCount([
                    'items',
                    'stores' => function ($query) use ($request) {
                        $query->when($request->zone_id, function ($q) use ($request) {
                            $q->where('zone_id', $request->zone_id);
                        });
                    }
                ])
                ->when($request->zone_id, function ($query) use ($request) {
                    $query->whereHas('zones', function ($query) use ($request) {
                        $query->where('zone_id', $request->zone_id);
                    })->notParcel();
                })
                ->active()
                ->get();
            }

            return array_map(function ($item) {
                return $item;
            }, $modules->toArray());
        });

        return response()->json($modules);
    }

    public function topOffer(Request $request)
    {
        return response()->json([
            'discount' => 0,
            'discount_type' => 'percent',
            'modules' => [],
        ], 200);
    }

}
