<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeBloc;
use Illuminate\Http\Request;

class TradeBlocController extends Controller
{
    public function index()
    {
        $blocs = TradeBloc::where('status', 'active')->get();
        return response()->json(['blocs' => $blocs], 200);
    }

    public function show($slug)
    {
        $bloc = TradeBloc::where('slug', $slug)->where('status', 'active')->first();
        if (!$bloc) {
            return response()->json(['error' => 'Bloc not found'], 404);
        }
        return response()->json(['bloc' => $bloc], 200);
    }

    public function corridors($slug)
    {
        $bloc = TradeBloc::where('slug', $slug)->where('status', 'active')->first();
        if (!$bloc) {
            return response()->json(['error' => 'Bloc not found'], 404);
        }
        $corridors = $bloc->corridors()->get();
        return response()->json(['corridors' => $corridors], 200);
    }

    public function reportCorridors(Request $request)
    {
        $blocSlug = $request->bloc;
        $query = \App\Models\TradeContract::with(['tradeRequest', 'tradeQuote']);

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
            ];
        })->values();

        return response()->json(['corridor_stats' => $corridorStats], 200);
    }

    public function reportCommodities(Request $request)
    {
        $query = \App\Models\TradeContract::with(['tradeRequest.product']);
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

        return response()->json(['commodity_stats' => $commodityStats], 200);
    }

    public function reportCountries(Request $request)
    {
        $query = \App\Models\TradeContract::with(['tradeRequest']);
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
            return ['country' => $country, 'role' => 'origin', 'trade_count' => $group->count(), 'total_value' => round($group->sum('contract_amount'), 2)];
        });
        $destStats = $contracts->groupBy(fn($c) => $c->tradeRequest?->destination_country ?? 'Unknown')->map(function ($group, $country) {
            return ['country' => $country, 'role' => 'destination', 'trade_count' => $group->count(), 'total_value' => round($group->sum('contract_amount'), 2)];
        });

        return response()->json([
            'origin_stats' => $originStats->values(),
            'destination_stats' => $destStats->values(),
        ], 200);
    }
}
