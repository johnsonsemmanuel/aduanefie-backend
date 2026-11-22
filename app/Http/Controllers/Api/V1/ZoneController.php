<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Zone;
use App\Http\Controllers\Controller;
use App\Services\Markets\ZoneAccessService;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Validator;
class ZoneController extends Controller
{
    public function get_zones(Request $request)
    {
        /** @var ZoneAccessService $zoneAccess */
        $zoneAccess = app(ZoneAccessService::class);
        $countryHeader = $request->header('X-Market-Country');
        $countryQuery = $request->query('country_code');
        $query = Zone::where('status', 1);
        if ($zoneAccess->isDeclared($countryHeader, $countryQuery)) {
            // Unsupported markets resolve to null and therefore match no zone.
            $query->where('country_code', $zoneAccess->resolveCountry($countryHeader, $countryQuery));
        }
        $zones = $query->get();
        foreach($zones as $zone){
            $area = json_decode($zone->coordinates[0]->toJson(),true);
            $zone['formated_coordinates']=Helpers::format_coordiantes($area['coordinates']);
        }
        return response()->json($zones, 200);
    }

    public function zonesCheck(Request $request){
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
            'zone_id' => 'required',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        /** @var ZoneAccessService $zoneAccess */
        $zoneAccess = app(ZoneAccessService::class);
        $countryHeader = $request->header('X-Market-Country');
        $countryQuery = $request->query('country_code');
        $zoneQuery = Zone::where('id',$request->zone_id)
            ->whereContains('coordinates', new Point($request->lat, $request->lng, POINT_SRID));
        if ($zoneAccess->isDeclared($countryHeader, $countryQuery)) {
            $zoneQuery->where('country_code', $zoneAccess->resolveCountry($countryHeader, $countryQuery));
        }
        $zone = $zoneQuery->exists();

        return response()->json($zone, 200);

    }

}
