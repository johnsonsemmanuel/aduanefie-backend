<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\SMS_module;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AgroPriceController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('agro_market_prices');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('commodity', 'like', "%{$search}%")
                    ->orWhere('market_hub', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $prices = $query->orderBy('commodity')->orderBy('market_hub')->get();
        $activeStorePhonesCount = DB::table('stores')
            ->where('status', 1)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();

        return view('admin-views.agro-prices.index', compact('prices', 'activeStorePhonesCount'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'commodity' => 'required|string|max:120',
            'category' => 'required|string|max:80',
            'unit' => 'required|string|max:60',
            'market_hub' => 'required|string|max:80',
            'wholesale_price_ghs' => 'required|numeric|min:0',
            'retail_price_ghs' => 'required|numeric|min:0',
            'price_change_pct' => 'nullable|numeric',
            'trend' => 'required|in:up,down,stable',
            'source_platform' => 'nullable|string|max:120',
        ]);

        DB::table('agro_market_prices')->insert([
            'commodity' => trim($data['commodity']),
            'category' => trim($data['category']),
            'unit' => trim($data['unit']),
            'market_hub' => trim($data['market_hub']),
            'wholesale_price_ghs' => round((float) $data['wholesale_price_ghs'], 2),
            'retail_price_ghs' => round((float) $data['retail_price_ghs'], 2),
            'price_change_pct' => round((float) ($data['price_change_pct'] ?? 0), 2),
            'trend' => $data['trend'],
            'source_platform' => trim($data['source_platform'] ?? 'Aduanefie Field Desk'),
            'is_active' => $request->boolean('is_active', true),
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Toastr::success(translate('messages.added_successfully'));
        return back();
    }

    public function syncLive()
    {
        Artisan::call('agro-prices:sync');
        Toastr::success(trim(Artisan::output()) ?: 'Synced live Ghana commodity prices.');
        return back();
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'wholesale_price_ghs' => 'required|numeric|min:0',
            'retail_price_ghs' => 'required|numeric|min:0',
            'price_change_pct' => 'nullable|numeric',
            'trend' => 'required|in:up,down,stable',
            'market_hub' => 'required|string|max:80',
            'source_platform' => 'nullable|string|max:120',
        ]);

        DB::table('agro_market_prices')
            ->where('id', $id)
            ->update([
                'wholesale_price_ghs' => round((float) $data['wholesale_price_ghs'], 2),
                'retail_price_ghs' => round((float) $data['retail_price_ghs'], 2),
                'price_change_pct' => round((float) ($data['price_change_pct'] ?? 0), 2),
                'trend' => $data['trend'],
                'market_hub' => trim($data['market_hub']),
                'source_platform' => trim($data['source_platform'] ?? 'Aduanefie Field Desk'),
                'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : false,
                'recorded_at' => now(),
                'updated_at' => now(),
            ]);

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }

    public function destroy($id)
    {
        DB::table('agro_market_prices')->where('id', $id)->delete();
        Toastr::success(translate('messages.deleted_successfully'));
        return back();
    }

    public function sendSmsAlerts(Request $request)
    {
        $request->validate([
            'test_phone' => 'nullable|string|max:30',
            'custom_note' => 'nullable|string|max:120',
        ]);

        $topPrices = DB::table('agro_market_prices')
            ->where('is_active', 1)
            ->orderByDesc('recorded_at')
            ->limit(4)
            ->get();

        if ($topPrices->isEmpty()) {
            Toastr::warning('No active agro prices available to broadcast.');
            return back();
        }

        $summaryParts = [];
        foreach ($topPrices as $row) {
            $summaryParts[] = "{$row->commodity} ({$row->market_hub}): GHS {$row->wholesale_price_ghs}/{$row->unit}";
        }

        $message = 'Aduanefie Agro Index: ' . implode('; ', $summaryParts);
        if ($request->filled('custom_note')) {
            $message .= ' | ' . trim($request->input('custom_note'));
        }

        if ($request->filled('test_phone')) {
            $phones = [trim($request->input('test_phone'))];
        } else {
            $phones = DB::table('stores')
                ->where('status', 1)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->distinct()
                ->pluck('phone')
                ->toArray();
        }

        if (empty($phones)) {
            Toastr::warning('No recipient phone numbers found.');
            return back();
        }

        $sent = 0;
        $failed = 0;
        foreach ($phones as $phone) {
            $result = SMS_module::send($phone, '', $message);
            if ($result === 'success') {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent > 0 && $failed === 0) {
            Toastr::success("Agro price SMS alert sent to {$sent} recipient(s).");
        } elseif ($sent > 0) {
            Toastr::warning("Agro price SMS alert sent to {$sent} recipient(s), {$failed} failed.");
        } else {
            Toastr::error('SMS broadcast failed. Please verify Hubtel SMS is enabled and configured under 3rd Party -> SMS Module.');
        }

        return back();
    }
}
