<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cart;
use App\Models\Item;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ItemCampaign;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function get_carts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $moduleId = $request->header('moduleId');

        if (!$moduleId) {
            $defaultModule = \App\Models\Module::active()->orderBy('id')->first();
            $moduleId = $defaultModule ? $defaultModule->id : null;
        }

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$moduleId)->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function add_to_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
            'item_id' => 'required|integer',
            'model' => 'required|string|in:Item,ItemCampaign',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $moduleId = $request->header('moduleId');

        if (!$moduleId) {
            $defaultModule = \App\Models\Module::active()->orderBy('id')->first();
            $moduleId = $defaultModule ? $defaultModule->id : null;
        }

        $model = $request->model === 'Item' ? 'App\Models\Item' : 'App\Models\ItemCampaign';
        $item = $request->model === 'Item' ? Item::find($request->item_id) : ItemCampaign::find($request->item_id);

        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item_not_found', 'message' => translate('messages.item_not_found')]]
            ], 404);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user_id, $is_guest, $moduleId, $model, $item) {
            $cart = Cart::where('item_id', $request->item_id)
                ->where('item_type', $model)
                ->where('variation', json_encode($request->variation ?? []))
                ->where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->where('module_id', $moduleId)
                ->lockForUpdate()
                ->first();

            if ($item->maximum_cart_quantity && (($cart?->quantity ?? 0) + $request->quantity > $item->maximum_cart_quantity)) {
                return response()->json([
                    'errors' => [
                        ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                    ]
                ], 403);
            }

            if ($cart) {
                $cart->quantity = $cart->quantity + $request->quantity;
                $cart->price = $request->price;
                $cart->save();
            } else {
                $cart = new Cart();
                $cart->user_id = $user_id;
                $cart->module_id = $moduleId;
                $cart->item_id = $request->item_id;
                $cart->is_guest = $is_guest;
                $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : json_encode([]);
                $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : json_encode([]);
                $cart->item_type = $request->model;
                $cart->price = $request->price;
                $cart->quantity = $request->quantity;
                $cart->variation = isset($request->variation) ? json_encode($request->variation) : json_encode([]);
                $cart->save();

                $item->carts()->save($cart);
            }

            $carts = Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->where('module_id', $moduleId)
                ->with('item')
                ->get()
                ->map(function ($data) {
                    $data->add_on_ids = json_decode($data->add_on_ids, true);
                    $data->add_on_qtys = json_decode($data->add_on_qtys, true);
                    $data->variation = json_decode($data->variation, true);
                    $data->item = Helpers::cart_product_data_formatting($data->item, $data->variation, $data->add_on_ids,
                        $data->add_on_qtys, false, app()->getLocale());
                    return $data;
                });

            return response()->json($carts, 200);
        });
    }

    public function update_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $moduleId = $request->header('moduleId');

        if (!$moduleId) {
            $defaultModule = \App\Models\Module::active()->orderBy('id')->first();
            $moduleId = $defaultModule ? $defaultModule->id : null;
        }

        $cart = Cart::find($request->cart_id);
        $item = $cart->item_type === 'App\Models\Item' ? Item::find($cart->item_id) : ItemCampaign::find($cart->item_id);
        if($item->maximum_cart_quantity && ($request->quantity>$item->maximum_cart_quantity)){
            return response()->json([
                'errors' => [
                    ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                ]
            ], 403);
        }

        $cart->user_id = $user_id;
        $cart->module_id = $moduleId;
        $cart->is_guest = $is_guest;
        $cart->add_on_ids = isset($request->add_on_ids)?json_encode($request->add_on_ids):$cart->add_on_ids;
        $cart->add_on_qtys = isset($request->add_on_qtys)?json_encode($request->add_on_qtys):$cart->add_on_qtys;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = isset($request->variation)?json_encode($request->variation):$cart->variation;
        $cart->save();

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$moduleId)->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function remove_cart_item(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $moduleId = $request->header('moduleId');

        if (!$moduleId) {
            $defaultModule = \App\Models\Module::active()->orderBy('id')->first();
            $moduleId = $defaultModule ? $defaultModule->id : null;
        }

        $cart = Cart::find($request->cart_id);
        $cart->delete();

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$moduleId)->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function remove_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $moduleId = $request->header('moduleId');

        if (!$moduleId) {
            $defaultModule = \App\Models\Module::active()->orderBy('id')->first();
            $moduleId = $defaultModule ? $defaultModule->id : null;
        }

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$moduleId)->get();

        foreach($carts as $cart){
            $cart->delete();
        }


        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$moduleId)->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function discount_eligibility(Request $request)
    {
        $storeId = $request->query('store_id');
        $store = null;
        if ($storeId) {
            $store = is_numeric($storeId)
                ? \App\Models\Store::with('discount')->find($storeId)
                : \App\Models\Store::with('discount')->where('slug', $storeId)->first();
        }

        $storeDiscount = $store ? Helpers::get_store_discount($store) : null;
        if (!$storeDiscount || (float) ($storeDiscount['discount'] ?? 0) <= 0) {
            return response()->json([
                'source' => null,
                'percentage' => 0,
                'min_purchase' => 0,
                'max_discount' => null,
                'qualifying_amount' => 0,
                'shortfall' => 0,
                'is_qualified' => false,
                'discount_amount' => 0,
            ], 200);
        }

        $user_id = $request->user ? $request->user->id : $request->query('guest_id');
        $is_guest = $request->user ? 0 : 1;
        $qualifyingAmount = 0.0;

        if ($user_id) {
            $carts = Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->where('item_type', 'App\\Models\\Item')
                ->get();

            foreach ($carts as $cart) {
                if ($cart->item && (int) $cart->item->store_id === (int) $store->id) {
                    $qualifyingAmount += (float) $cart->price * (int) $cart->quantity;
                }
            }
        }

        $percentage = (float) ($storeDiscount['discount'] ?? 0);
        $minPurchase = (float) ($storeDiscount['min_purchase'] ?? 0);
        $maxDiscount = $storeDiscount['max_discount'] !== null ? (float) $storeDiscount['max_discount'] : null;
        $shortfall = max(0.0, $minPurchase - $qualifyingAmount);
        $isQualified = $minPurchase <= 0 || $qualifyingAmount >= $minPurchase;
        $discountAmount = $isQualified ? round($qualifyingAmount * ($percentage / 100), 2) : 0.0;
        if ($maxDiscount !== null && $maxDiscount > 0 && $discountAmount > $maxDiscount) {
            $discountAmount = $maxDiscount;
        }

        return response()->json([
            'source' => 'store_discount',
            'percentage' => $percentage,
            'min_purchase' => $minPurchase,
            'max_discount' => $maxDiscount,
            'qualifying_amount' => round($qualifyingAmount, 2),
            'shortfall' => round($shortfall, 2),
            'is_qualified' => $isQualified,
            'discount_amount' => $discountAmount,
        ], 200);
    }
}
