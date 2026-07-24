<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\PersonalizationService;
use App\CentralLogics\CategoryLogic;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\FarmUpdate;
use App\Traits\ItemFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    use ItemFilter;

    public function get_stores(Request $request, $filter_data="all")
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $store_type = $request->query('store_type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $filter = $request->query('filter', '');
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, "[]"), ',')) : '';
        $rating_count = $request->query('rating_count');
        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : null;
        $stores = StoreLogic::get_stores($zone_id, $filter_data, $type, $store_type, $request['limit'], $request['offset'], $request->query('featured'), $longitude, $latitude, $filter, $rating_count, $this->storeFilterInputs($request), auth('api')->id(), is_numeric($moduleId) ? (int) $moduleId : null);
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        return response()->json($stores, 200);
    }

    public function get_latest_stores(Request $request, $filter_data="all")
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $stores = StoreLogic::get_latest_stores($zone_id, $request['limit'], $request['offset'], $type,$longitude,$latitude,auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);
        $stores['stores'] = StoreLogic::add_store_main_categories($stores['stores']);

        return response()->json($stores, 200);
    }

    public function get_verified_stores(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $stores = StoreLogic::get_verified_stores(
            zone_id: $zone_id,
            limit: $request['limit'],
            offset: $request['offset'],
            type: $type,
            longitude: $longitude,
            latitude: $latitude,
        );
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        return response()->json($stores, 200);
    }

    public function get_distance_wise_stores(Request $request, $filter_data="all")
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $stores = StoreLogic::get_distance_wise_stores($zone_id, $request['limit'], $request['offset'], $type, $longitude, $latitude, $request['name'], auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        return response()->json($stores, 200);
    }

    public function get_popular_stores(Request $request)
    {
        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $stores = StoreLogic::get_popular_stores($zone_id, $request['limit'], $request['offset'], $type,$longitude,$latitude,auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        return response()->json($stores, 200);
    }

    public function get_discounted_stores(Request $request)
    {
        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $stores = StoreLogic::get_discounted_stores($zone_id, $request['limit'], $request['offset'], $type,$longitude,$latitude, null, null, null, auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        return response()->json($stores, 200);
    }

    public function get_top_rated_stores(Request $request)
    {
        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $stores = StoreLogic::get_top_rated_stores($zone_id, $request['limit'], $request['offset'], $type,$longitude,$latitude,auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);

        usort($stores['stores'], function ($a, $b) {
            $key = 'avg_rating';
            return $b[$key] - $a[$key];
        });

        return response()->json($stores, 200);
    }

    public function get_popular_store_items(Request $request, $id)
    {
        Helpers::setZoneIds($request);
        $zone_ids = $request->header('zoneId') ? json_decode($request->header('zoneId'), true) : null;

        $items = Item::
        when($zone_ids, function ($query) use ($zone_ids) {
            $query->whereIn('store_id', \App\Models\Store::whereIn('zone_id', $zone_ids)->pluck('id'));
        })
        ->when(is_numeric($id),function ($qurey) use($id){
            $qurey->where('store_id', $id);
        })
        ->when(!is_numeric($id), function ($query) use ($id) {
            $query->whereHas('store', function ($q) use ($id) {
                $q->where('slug', $id);
            });
        })
        ->active()->popular()->limit(10)->get();
        $items = Helpers::product_data_formatting($items, true, true, app()->getLocale());

        return response()->json($items, 200);
    }

    public function get_details(Request $request,$id)
    {
        Helpers::setZoneIds($request);
        $zone_ids = $request->header('zoneId') ? json_decode($request->header('zoneId'), true) : null;

        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $store = StoreLogic::get_store_details($id,$longitude,$latitude);
        if($store)
        {
            if ($zone_ids && !in_array($store->zone_id, $zone_ids)) {
                return response()->json([
                    'errors' => ['code' => 'store-001', 'message' => translate('messages.store_not_found_in_your_zone')]
                ], 404);
            }
            if(auth('api')->check()){
                Helpers::visitor_log(model: 'store', user_id: auth('api')->id(), visitor_log_id: $store->id, order_count: false);
                PersonalizationService::recordStoreAction(auth('api')->id(), $store->id, 'store_view');
            }
            $category_ids = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
            ->where('items.store_id', $store->id)
            ->where('categories.status',1)
            ->groupBy('categories','positions')
            ->get();

            $storeId = $store->id;
            $store = Helpers::store_data_formatting($store);
            $store['category_ids'] = array_map('intval', $category_ids->pluck('categories')->toArray());
            $store['category_details'] = Category::whereIn('id',$store['category_ids'])->get();
            $store['price_range']  = Item::withoutGlobalScopes()->where('store_id', $storeId)
            ->select(DB::raw('MIN(price) AS min_price, MAX(price) AS max_price'))
            ->get(['min_price','max_price'])->toArray();
            $store['store_categories'] = Helpers::storeCategoryStatus()
                ? \App\Models\StoreCategory::active()->where('store_id', $storeId)->orderBy('priority', 'desc')->get()
                : [];
        }
        return response()->json($store, 200);
    }

    public function get_searched_stores(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        if(auth('api')->check()){
            PersonalizationService::recordSearchAction(auth('api')->id(), $request['name'], config('module.current_module_data') ? (int)config('module.current_module_data')['id'] : null);
        }

        $filters = $this->resolveSearchFilters($request);
        $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

        $stores = StoreLogic::search_stores($request['name'], $zone_id, $request->category_id,$request['limit'], $request['offset'], $type,$longitude,$latitude,null,null,null,null,auth('api')->id(), $request, $additional_data);
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);
        return response()->json($stores, 200);
    }

    public function reviews(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $id = $request['store_id'];


        $reviews = Review::with(['customer', 'item'])
        ->whereHas('item', function($query)use($id){
            return $query->where('store_id', $id);
        })
        ->active()->latest()->get();

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            $temp['item_image'] = null;
            $temp['customer_name'] = null;
            // $temp->item=null;
            if($temp->item)
            {
                $temp['item_name'] = $temp->item->name;
                $temp['item_image'] = $temp->item->image;
                $temp['item_image_full_url'] = $temp->item->image_full_url;
                if(count($temp->item->translations)>0)
                {
                    $translate = array_column($temp->item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
                unset($temp->item);
                $temp['item'] = Helpers::product_data_formatting($temp->item, false, false, app()->getLocale());
            }
            if($temp->customer)
            {
                $temp['customer_name'] = $temp->customer->f_name.' '.$temp->customer->l_name;
            }

            unset($temp['customer']);
            array_push($storage, $temp);
        }

        return response()->json($storage, 200);
    }


    public function get_recommended_stores(Request $request){


        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude') ?? 0;
        $latitude= $request->header('latitude') ?? 0;
        $stores = StoreLogic::get_recommended_stores($zone_id, $request['limit'], $request['offset'], $type,$longitude,$latitude,auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);
        $stores['stores'] = StoreLogic::add_store_main_categories($stores['stores']);

        return response()->json($stores, 200);
    }

    public function get_combined_data(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $data_type = $request->query('data_type', 'all');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);
        $longitude =  $request->header('longitude') ?? 0;
        $latitude =  $request->header('latitude') ?? 0;
        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $rating_count = $request->query('rating_count');

        switch ($data_type) {
            case 'searched':
                $validator = Validator::make($request->all(), ['name' => 'required']);
                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }
                $name = $request->input('name');

                if(auth('api')->check()){
                    PersonalizationService::recordSearchAction(auth('api')->id(), $name, config('module.current_module_data') ? (int)config('module.current_module_data')['id'] : null);
                }

                $paginator = StoreLogic::search_stores($name, $zone_id, $request->category_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count, null, null, auth('api')->id());
                break;

            case 'discounted':

                $paginator = StoreLogic::get_discounted_stores($zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required|array',
                    'category_ids.*' => 'integer'
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $category_ids = $request->input('category_ids');

                $paginator = CategoryLogic::category_stores($category_ids, $zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count, null, auth('api')->id());
                break;

            default:
                $filter_data = $request->query('filter_data', 'all');
                $store_type = $request->query('store_type', 'all');
                $featured = $request->query('featured');
                $paginator = StoreLogic::get_stores($zone_id, $filter_data, $type, $store_type, $limit, $offset, $featured, $longitude, $latitude, $filter, $rating_count, null, auth('api')->id());
                break;
        }

        $paginator['stores'] = Helpers::store_data_formatting($paginator['stores'], true);
        return response()->json($paginator, 200);
    }

    public function get_top_offer_near_me(Request $request)
    {
        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');

        $stores = StoreLogic::get_top_offer_near_me(zone_id:$zone_id, limit:$request['limit'], offset: $request['offset'], type: $type, longitude:$longitude,latitude: $latitude,
                    name:$request->name, sort: $request->sort_by ,halal: $request->halal, user_id: auth('api')->id());
        $stores['stores'] = Helpers::store_data_formatting($stores['stores'], true);


        return response()->json($stores, 200);
    }

    public function get_quick_delivery_stores(Request $request){
        Helpers::setZoneIds($request);
        $type = $request->query('type', 'all');
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);
        $isAd = filter_var($request->query('ad', false), FILTER_VALIDATE_BOOLEAN);
        $withItems = isset($request->with_items)?$request->with_items:false;

        $filter = $this->storeFilterInputs($request);

        $stores = StoreLogic::get_quick_delivery_stores($zone_id, $limit, $offset, $type, $longitude, $latitude, $isAd, $filter,$withItems, auth('api')->id());

        return response()->json($stores, 200);
    }

    public function get_exclusive_deals(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : null;
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $filter = $this->storeFilterInputs($request);

        $data = StoreLogic::get_exclusive_deals(
            $zone_id,
            is_numeric($moduleId) ? (int) $moduleId : null,
            $longitude,
            $latitude,
            $limit,
            $offset,
            $filter,
            auth('api')->id(),
        );

        return response()->json($data, 200);
    }

    public function get_organic_stores(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $limit = $request->query('limit', 25);
        $offset = $request->query('offset', 1);

        $storeIds = Item::where('organic', 1)
            ->whereHas('store', function ($query) use ($zone_id, $type, $longitude, $latitude) {
                $query->where('status', 1)
                    ->when(config('module.current_module_data'), function ($q) {
                        $q->where('module_id', config('module.current_module_data')['id']);
                    })
                    ->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->pluck('store_id')
            ->unique()
            ->toArray();

        $stores = Store::whereIn('id', $storeIds)
            ->active();

        $storeList = $stores->paginate($limit, ['*'], 'page', $offset);
        $storeList->getCollection()->transform(function ($store) {
            return Helpers::store_data_formatting($store, true);
        });

        return response()->json([
            'total_size' => $storeList->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $storeList->items(),
        ], 200);
    }

    public function get_farm_updates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required_without:store_slug',
            'store_slug' => 'required_without:store_id',
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $query = FarmUpdate::query()->with('store');

        if (is_numeric($request->store_id)) {
            $query->where('store_id', $request->store_id);
        } elseif ($request->filled('store_slug')) {
            $query->whereHas('store', function ($q) use ($request) {
                $q->where('slug', $request->store_slug);
            });
        }

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);
        $updates = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $updates->total(),
            'limit' => $limit,
            'offset' => $offset,
            'farm_updates' => $updates->items(),
        ], 200);
    }
}
