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

    public function agro_prices(Request $request)
    {
        $category = $request->query('category');
        $hub = $request->query('market_hub');
        $search = trim((string) $request->query('search', ''));

        $prices = collect();
        if (Schema::hasTable('agro_market_prices')) {
            $query = DB::table('agro_market_prices')->where('is_active', true);
            if ($category && $category !== 'all') {
                $query->where('category', $category);
            }
            if ($hub && $hub !== 'all') {
                $query->where('market_hub', $hub);
            }
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('commodity', 'like', "%{$search}%")
                      ->orWhere('market_hub', 'like', "%{$search}%")
                      ->orWhere('source_platform', 'like', "%{$search}%");
                });
            }
            $prices = $query->orderBy('id')->get();
        }

        if ($prices->isEmpty() && ! $category && ! $hub && $search === '') {
            $prices = collect(self::defaultGhanaAgroPrices());
        }

        $allForFilters = Schema::hasTable('agro_market_prices')
            ? DB::table('agro_market_prices')->where('is_active', true)->get()
            : collect(self::defaultGhanaAgroPrices());

        return response()->json([
            'prices' => $prices->values(),
            'categories' => $allForFilters->pluck('category')->unique()->values(),
            'hubs' => $allForFilters->pluck('market_hub')->unique()->values(),
            'currency' => 'GHS',
            'currency_symbol' => 'GH₵',
            'updated_at' => now()->toIso8601String(),
        ], 200);
    }

    public static function defaultGhanaAgroPrices(): array
    {
        return [
            ['id' => 1, 'commodity' => 'White Maize (Grade 2)', 'category' => 'Grains & Cereals', 'unit' => '100 kg bag', 'market_hub' => 'Techiman / Ejura (GCX)', 'wholesale_price_ghs' => 330.00, 'retail_price_ghs' => 385.00, 'price_change_pct' => 1.8, 'trend' => 'up', 'source_platform' => 'GCX / MoFA SRID'],
            ['id' => 2, 'commodity' => 'Yellow Maize (Grade 2)', 'category' => 'Grains & Cereals', 'unit' => '100 kg bag', 'market_hub' => 'Kumasi / Wenchi (GCX)', 'wholesale_price_ghs' => 365.00, 'retail_price_ghs' => 420.00, 'price_change_pct' => 2.4, 'trend' => 'up', 'source_platform' => 'GCX Spot'],
            ['id' => 3, 'commodity' => 'Local Rice (Milled)', 'category' => 'Grains & Cereals', 'unit' => '50 kg bag', 'market_hub' => 'Tamale / Accra', 'wholesale_price_ghs' => 520.00, 'retail_price_ghs' => 590.00, 'price_change_pct' => 0.9, 'trend' => 'up', 'source_platform' => 'GCX / MoFA SRID'],
            ['id' => 4, 'commodity' => 'Soya Beans (Grade 2)', 'category' => 'Legumes & Oilseeds', 'unit' => '100 kg bag', 'market_hub' => 'Tamale / Kumasi', 'wholesale_price_ghs' => 640.00, 'retail_price_ghs' => 720.00, 'price_change_pct' => 3.1, 'trend' => 'up', 'source_platform' => 'GCX Spot'],
            ['id' => 5, 'commodity' => 'Tomatoes (Fresh)', 'category' => 'Vegetables', 'unit' => '52 kg crate', 'market_hub' => 'Agbogbloshie (Accra)', 'wholesale_price_ghs' => 1150.00, 'retail_price_ghs' => 1380.00, 'price_change_pct' => -4.2, 'trend' => 'down', 'source_platform' => 'Esoko / MoFA SRID'],
            ['id' => 6, 'commodity' => 'Pona Yam', 'category' => 'Tubers & Roots', 'unit' => '100 tubers', 'market_hub' => 'Techiman Market', 'wholesale_price_ghs' => 1850.00, 'retail_price_ghs' => 2200.00, 'price_change_pct' => 1.2, 'trend' => 'up', 'source_platform' => 'Esoko Ghana'],
            ['id' => 7, 'commodity' => 'Cassava (Fresh Tubers)', 'category' => 'Tubers & Roots', 'unit' => '91 kg bag', 'market_hub' => 'Eastern / Bono Hub', 'wholesale_price_ghs' => 75.00, 'retail_price_ghs' => 105.00, 'price_change_pct' => 0.0, 'trend' => 'stable', 'source_platform' => 'MoFA SRID'],
            ['id' => 8, 'commodity' => 'Gari (Processed Cassava)', 'category' => 'Tubers & Roots', 'unit' => '50 kg bag', 'market_hub' => 'Volta / Central Hub', 'wholesale_price_ghs' => 210.00, 'retail_price_ghs' => 260.00, 'price_change_pct' => 1.5, 'trend' => 'up', 'source_platform' => 'Esoko Ghana'],
            ['id' => 9, 'commodity' => 'Plantain (Apantu)', 'category' => 'Tubers & Roots', 'unit' => 'Large Bunch', 'market_hub' => 'Kumasi Central', 'wholesale_price_ghs' => 85.00, 'retail_price_ghs' => 115.00, 'price_change_pct' => 2.8, 'trend' => 'up', 'source_platform' => 'MoFA SRID'],
            ['id' => 10, 'commodity' => 'Sorghum', 'category' => 'Grains & Cereals', 'unit' => '100 kg bag', 'market_hub' => 'Tamale / Wa Hub', 'wholesale_price_ghs' => 580.00, 'retail_price_ghs' => 650.00, 'price_change_pct' => 0.5, 'trend' => 'up', 'source_platform' => 'GCX Spot'],
            ['id' => 11, 'commodity' => 'Groundnuts (Shelled)', 'category' => 'Legumes & Oilseeds', 'unit' => '100 kg bag', 'market_hub' => 'Tamale / Bolga', 'wholesale_price_ghs' => 980.00, 'retail_price_ghs' => 1120.00, 'price_change_pct' => 1.9, 'trend' => 'up', 'source_platform' => 'Esoko Ghana'],
            ['id' => 12, 'commodity' => 'Sesame Seed', 'category' => 'Legumes & Oilseeds', 'unit' => '50 kg bag', 'market_hub' => 'Saboba / Tamale (GCX)', 'wholesale_price_ghs' => 760.00, 'retail_price_ghs' => 860.00, 'price_change_pct' => 2.1, 'trend' => 'up', 'source_platform' => 'GCX Spot'],
        ];
    }
}
