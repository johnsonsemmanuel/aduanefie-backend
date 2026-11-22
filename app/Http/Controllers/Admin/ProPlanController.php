<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProPlanController extends Controller
{
    public function index()
    {
        if (! Schema::hasTable('pro_customer_plans')) {
            return view('admin-views.pro-plans.index', [
                'plans' => collect(),
                'activeSubscribers' => 0,
            ]);
        }

        $plans = DB::table('pro_customer_plans')->orderBy('price')->get();
        $activeSubscribers = Schema::hasTable('pro_customer_subscriptions')
            ? DB::table('pro_customer_subscriptions')
                ->where('status', 'active')
                ->where('end_at', '>', now())
                ->count()
            : 0;

        return view('admin-views.pro-plans.index', compact('plans', 'activeSubscribers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'tagline' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'validity_days' => 'required|integer|min:1|max:3650',
            'min_order_amount' => 'required|numeric|min:0',
        ]);

        DB::table('pro_customer_plans')->insert([
            'name' => trim((string) $request->input('name')),
            'tagline' => $request->input('tagline') ? trim((string) $request->input('tagline')) : null,
            'price' => (float) $request->input('price'),
            'validity_days' => (int) $request->input('validity_days'),
            'benefit_type' => 'delivery_fee',
            'offer_type' => 'free',
            'charge_discount_percentage' => 100.00,
            'min_order_status' => (float) $request->input('min_order_amount') > 0 ? 1 : 0,
            'min_order_amount' => (float) $request->input('min_order_amount'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Toastr::success('Pro membership plan created.');
        return back();
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'tagline' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'validity_days' => 'required|integer|min:1|max:3650',
            'min_order_amount' => 'required|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        DB::table('pro_customer_plans')->where('id', $id)->update([
            'name' => trim((string) $request->input('name')),
            'tagline' => $request->input('tagline') ? trim((string) $request->input('tagline')) : null,
            'price' => (float) $request->input('price'),
            'validity_days' => (int) $request->input('validity_days'),
            'min_order_status' => (float) $request->input('min_order_amount') > 0 ? 1 : 0,
            'min_order_amount' => (float) $request->input('min_order_amount'),
            'status' => $request->boolean('status'),
            'updated_at' => now(),
        ]);

        Toastr::success('Pro membership plan updated.');
        return back();
    }
}
