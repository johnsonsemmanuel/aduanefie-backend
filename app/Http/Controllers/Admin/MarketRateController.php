<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrencyRate;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Manual entry point for the cross-border reference data used by shipping
 * quotes. Seeded rates are labelled baselines; admins replace them here with
 * operator-confirmed values or refresh FX from the live provider.
 */
class MarketRateController extends Controller
{
    public function index()
    {
        $currencyRates = CurrencyRate::orderBy('base_currency')
            ->orderBy('quote_currency')
            ->get();
        $shippingRates = ShippingRate::with('method')
            ->orderBy('origin_country')
            ->orderBy('destination_country')
            ->get();
        $shippingMethods = ShippingMethod::orderBy('sort_order')->get();

        return view(
            'admin-views.market-rates.index',
            compact('currencyRates', 'shippingRates', 'shippingMethods')
        );
    }

    public function storeCurrency(Request $request)
    {
        $data = $request->validate([
            'base_currency' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/',
            'quote_currency' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/|different:base_currency',
            'rate' => 'required|numeric|min:0.00000001',
            'effective_at' => 'nullable|date',
        ]);

        CurrencyRate::updateOrCreate(
            [
                'base_currency' => strtoupper($data['base_currency']),
                'quote_currency' => strtoupper($data['quote_currency']),
            ],
            [
                'rate' => (float) $data['rate'],
                'source' => 'admin_manual',
                'effective_at' => $data['effective_at'] ?: now(),
            ]
        );

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }

    public function updateCurrency(Request $request, $id)
    {
        $rate = CurrencyRate::findOrFail($id);
        $data = $request->validate([
            'rate' => 'required|numeric|min:0.00000001',
            'effective_at' => 'nullable|date',
        ]);

        $rate->update([
            'rate' => (float) $data['rate'],
            'source' => 'admin_manual',
            'effective_at' => $data['effective_at'] ?: now(),
        ]);

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }

    public function destroyCurrency($id)
    {
        CurrencyRate::findOrFail($id)->delete();
        Toastr::success(translate('messages.deleted_successfully'));
        return back();
    }

    public function storeShipping(Request $request)
    {
        $data = $request->validate([
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'origin_country' => 'required|string|size:2|regex:/^[A-Za-z]{2}$/',
            'destination_country' => 'required|string|size:2|regex:/^[A-Za-z]{2}$/',
            'min_weight' => 'required|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0|gt:min_weight',
            'base_rate' => 'required|numeric|min:0',
            'per_kg_rate' => 'required|numeric|min:0',
            'min_days' => 'nullable|integer|min:0',
            'max_days' => 'nullable|integer|min:0|gte:min_days',
            'currency_code' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/',
            'cod_allowed' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        ShippingRate::create([
            'shipping_method_id' => (int) $data['shipping_method_id'],
            'origin_country' => strtoupper($data['origin_country']),
            'destination_country' => strtoupper($data['destination_country']),
            'min_weight' => (float) $data['min_weight'],
            'max_weight' => isset($data['max_weight']) ? (float) $data['max_weight'] : null,
            'base_rate' => (float) $data['base_rate'],
            'per_kg_rate' => (float) $data['per_kg_rate'],
            'min_days' => $data['min_days'] ?? null,
            'max_days' => $data['max_days'] ?? null,
            'currency_code' => strtoupper($data['currency_code']),
            'source' => 'admin_manual',
            'effective_at' => now(),
            'cod_allowed' => (bool) ($data['cod_allowed'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        Toastr::success(translate('messages.added_successfully'));
        return back();
    }

    public function updateShipping(Request $request, $id)
    {
        $rate = ShippingRate::findOrFail($id);
        $data = $request->validate([
            'base_rate' => 'required|numeric|min:0',
            'per_kg_rate' => 'required|numeric|min:0',
            'min_days' => 'nullable|integer|min:0',
            'max_days' => 'nullable|integer|min:0|gte:min_days',
            'source' => 'nullable|string|max:80',
            'is_active' => 'nullable|boolean',
            'cod_allowed' => 'nullable|boolean',
        ]);

        $rate->update([
            'base_rate' => (float) $data['base_rate'],
            'per_kg_rate' => (float) $data['per_kg_rate'],
            'min_days' => $data['min_days'] ?? null,
            'max_days' => $data['max_days'] ?? null,
            'source' => $data['source'] ?: 'admin_manual',
            'effective_at' => now(),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'cod_allowed' => (bool) ($data['cod_allowed'] ?? false),
        ]);

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }

    public function destroyShipping($id)
    {
        ShippingRate::findOrFail($id)->delete();
        Toastr::success(translate('messages.deleted_successfully'));
        return back();
    }

    public function refreshFx()
    {
        Artisan::call('market:rates:refresh');
        Toastr::success(trim(Artisan::output()) ?: 'Market FX rates refreshed.');
        return back();
    }
}
