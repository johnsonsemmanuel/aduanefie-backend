<?php

namespace App\Http\Controllers;

use App\Models\TradeBloc;
use App\Models\TradeCorridor;
use App\Models\Item;
use App\Models\CustomsDuty;
use App\Models\TradeContract;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeBlocController extends Controller
{
    /**
     * Show regional trade bloc hub page with bloc-specific corridors.
     *
     * @return \Illuminate\Http\Response
     */
    public function hub($slug)
    {
        $bloc = TradeBloc::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $corridors = $bloc->corridors()->get();

        // Get commodities traded within this bloc
        $commodities = Item::whereIn('category_id', function ($query) {
            $query->select('id')->from('categories')->where('module_id', 20);
        })
        ->where('status', 1)
        ->limit(12)
        ->get();

        // Group corridors by origin country for display
        $corridorsByOrigin = $corridors->groupBy('origin_country');

        // Get customs duty regulations for bloc destination countries
        $memberCountries = json_decode($bloc->member_countries, true);
        $dutyRegulations = [];
        if (is_array($memberCountries)) {
            foreach ($memberCountries as $country) {
                $duties = CustomsDuty::forCountry($country)->get();
                $dutyRegulations[$country] = $duties;
            }
        }

        return view('trade-bloc.hub', compact('bloc', 'corridors', 'corridorsByOrigin', 'commodities', 'dutyRegulations', 'memberCountries'));
    }

    /**
     * Show all trade blocs listing page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $blocs = TradeBloc::active()->get();

        return view('trade-bloc.index', compact('blocs'));
    }

    /**
     * Trade report: volume by corridor.
     *
     * @return \Illuminate\Http\Response
     */
    public function reportCorridors(Request $request)
    {
        $blocSlug = $request->bloc;
        $query = TradeContract::with(['tradeRequest', 'tradeQuote']);

        if ($blocSlug) {
            $bloc = TradeBloc::where('slug', $blocSlug)->first();
            if ($bloc) {
                $memberCountries = json_decode($bloc->member_countries, true);
                $query->whereHas('tradeRequest', function ($q) use ($memberCountries) {
                    $q->whereIn('origin_country', $memberCountries)
                        ->whereIn('destination_country', $memberCountries);
                });
            }
        }

        $contracts = $query->get();

        $corridorStats = $contracts->groupBy(function ($c) {
            return $c->tradeRequest?->origin_country . ' → ' . $c->tradeRequest?->destination_country;
        })->map(function ($group, $corridor) {
            return [
                'corridor' => $corridor,
                'trade_count' => $group->count(),
                'total_value' => round($group->sum('contract_amount'), 2),
                'currency' => $group->first()->currency_code ?? 'GHS',
                'released_value' => round($group->where('payment_status', 'released')->sum('escrow_amount'), 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'bloc' => $blocSlug,
            'corridor_stats' => $corridorStats,
        ]);
    }

    /**
     * Trade report: volume by commodity.
     *
     * @return \Illuminate\Http\Response
     */
    public function reportCommodities(Request $request)
    {
        $query = TradeContract::with(['tradeRequest.product']);

        if ($request->bloc) {
            $bloc = TradeBloc::where('slug', $request->bloc)->first();
            if ($bloc) {
                $memberCountries = json_decode($bloc->member_countries, true);
                $query->whereHas('tradeRequest', function ($q) use ($memberCountries) {
                    $q->whereIn('origin_country', $memberCountries)
                        ->whereIn('destination_country', $memberCountries);
                });
            }
        }

        $contracts = $query->get();

        $commodityStats = $contracts->groupBy(function ($c) {
            return $c->tradeRequest?->product?->name ?? 'Unknown';
        })->map(function ($group, $commodity) {
            return [
                'commodity' => $commodity,
                'trade_count' => $group->count(),
                'total_volume' => $group->sum(fn($c) => $c->tradeRequest?->quantity ?? 0),
                'total_value' => round($group->sum('contract_amount'), 2),
                'currency' => $group->first()->currency_code ?? 'GHS',
            ];
        })->values();

        return response()->json([
            'success' => true,
            'bloc' => $request->bloc,
            'commodity_stats' => $commodityStats,
        ]);
    }

    /**
     * Trade report: volume by country.
     *
     * @return \Illuminate\Http\Response
     */
    public function reportCountries(Request $request)
    {
        $query = TradeContract::with(['tradeRequest']);

        if ($request->bloc) {
            $bloc = TradeBloc::where('slug', $request->bloc)->first();
            if ($bloc) {
                $memberCountries = json_decode($bloc->member_countries, true);
                $query->whereHas('tradeRequest', function ($q) use ($memberCountries) {
                    $q->whereIn('origin_country', $memberCountries)
                        ->whereIn('destination_country', $memberCountries);
                });
            }
        }

        $contracts = $query->get();

        $originStats = $contracts->groupBy(fn($c) => $c->tradeRequest?->origin_country ?? 'Unknown')->map(function ($group, $country) {
            return [
                'country' => $country,
                'role' => 'origin',
                'trade_count' => $group->count(),
                'total_value' => round($group->sum('contract_amount'), 2),
            ];
        });

        $destStats = $contracts->groupBy(fn($c) => $c->tradeRequest?->destination_country ?? 'Unknown')->map(function ($group, $country) {
            return [
                'country' => $country,
                'role' => 'destination',
                'trade_count' => $group->count(),
                'total_value' => round($group->sum('contract_amount'), 2),
            ];
        });

        return response()->json([
            'success' => true,
            'bloc' => $request->bloc,
            'origin_stats' => $originStats->values(),
            'destination_stats' => $destStats->values(),
        ]);
    }
}
