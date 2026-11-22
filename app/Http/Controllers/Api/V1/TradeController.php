<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class TradeController extends Controller
{
    /**
     * Active blocs from the database. The small static list is the same
     * reference data the blocs index has always used when the table is empty;
     * it is not user-generated market data.
     */
    private function blocRows()
    {
        if (Schema::hasTable('trade_blocs')) {
            $rows = DB::table('trade_blocs')->where('status', 'active')->get();
            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        return collect([
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
        ]);
    }

    public function blocs()
    {
        return response()->json(['blocs' => $this->blocRows()->values()], 200);
    }

    public function bloc($slug)
    {
        $bloc = $this->blocRows()->first(
            fn ($row) => data_get($row, 'slug') === $slug
        );

        if (! $bloc) {
            return response()->json(['message' => 'Trade bloc not found'], 404);
        }

        return response()->json(['bloc' => $bloc], 200);
    }

    public function blocCorridors($slug)
    {
        $bloc = $this->blocRows()->first(
            fn ($row) => data_get($row, 'slug') === $slug
        );

        if (! $bloc) {
            return response()->json(['message' => 'Trade bloc not found'], 404);
        }

        $members = json_decode(data_get($bloc, 'member_countries') ?: '[]', true) ?: [];
        $corridors = Schema::hasTable('trade_corridors')
            ? DB::table('trade_corridors')
                ->where('status', 'active')
                ->get()
                ->filter(fn ($corridor) => in_array($corridor->origin_country, $members, true)
                    || in_array($corridor->destination_country, $members, true))
                ->values()
            : collect();

        return response()->json(['corridors' => $corridors], 200);
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
        return $this->requests($request);
    }

    public function requests(Request $request)
    {
        if (! Schema::hasTable('trade_rfqs')) {
            return response()->json(['requests' => [], 'total' => 0], 200);
        }

        $query = DB::table('trade_rfqs')->orderByDesc('id');
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('trade_bloc') && $request->query('trade_bloc') !== 'all') {
            $query->where('trade_bloc', $request->query('trade_bloc'));
        }

        $rfqs = $query->limit(50)->get();
        $quotesByRfq = Schema::hasTable('trade_rfq_quotes')
            ? DB::table('trade_rfq_quotes')
                ->whereIn('trade_rfq_id', $rfqs->pluck('id'))
                ->orderBy('quoted_unit_price_ghs')
                ->get()
                ->groupBy('trade_rfq_id')
            : collect();

        $enriched = $rfqs->map(function ($rfq) use ($quotesByRfq) {
            $quotes = $quotesByRfq->get($rfq->id, collect());
            $rfq->quotes_count = $quotes->count();
            $rfq->quotes = $quotes->values();
            return $rfq;
        });

        return response()->json([
            'requests' => $enriched->values(),
            'total' => $enriched->count(),
        ], 200);
    }

    public function store_rfq(Request $request)
    {
        if (! Schema::hasTable('trade_rfqs')) {
            return response()->json(['message' => 'RFQ table not initialized yet.'], 503);
        }

        $data = $request->validate([
            'buyer_name' => 'required|string|max:120',
            'buyer_company' => 'nullable|string|max:150',
            'buyer_phone' => 'required|string|max:40',
            'buyer_email' => 'nullable|email|max:150',
            'commodity' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'quantity' => 'required|numeric|min:1',
            'unit' => 'required|string|max:60',
            'target_price_ghs' => 'nullable|numeric|min:0',
            'incoterm' => 'nullable|in:EXW,FOB,CIF,DDP',
            'origin_country' => 'nullable|string|max:80',
            'destination_country' => 'required|string|max:80',
            'destination_city' => 'nullable|string|max:120',
            'trade_bloc' => 'nullable|in:ECOWAS,AfCFTA,EAC,SADC',
            'requires_insurance' => 'nullable|boolean',
            'requires_phytosanitary' => 'nullable|boolean',
            'deadline_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $id = DB::table('trade_rfqs')->insertGetId([
            'user_id' => auth('api')->id(),
            'buyer_name' => trim($data['buyer_name']),
            'buyer_company' => isset($data['buyer_company']) ? trim($data['buyer_company']) : null,
            'buyer_phone' => trim($data['buyer_phone']),
            'buyer_email' => $data['buyer_email'] ?? null,
            'commodity' => trim($data['commodity']),
            'category' => trim($data['category'] ?? 'Grains & Cereals'),
            'quantity' => round((float) $data['quantity'], 2),
            'unit' => trim($data['unit']),
            'target_price_ghs' => isset($data['target_price_ghs']) ? round((float) $data['target_price_ghs'], 2) : null,
            'incoterm' => strtoupper($data['incoterm'] ?? 'DDP'),
            'origin_country' => trim($data['origin_country'] ?? 'Ghana'),
            'destination_country' => trim($data['destination_country']),
            'destination_city' => isset($data['destination_city']) ? trim($data['destination_city']) : null,
            'trade_bloc' => strtoupper($data['trade_bloc'] ?? 'ECOWAS'),
            'requires_insurance' => (bool) ($data['requires_insurance'] ?? true),
            'requires_phytosanitary' => (bool) ($data['requires_phytosanitary'] ?? true),
            'deadline_date' => $data['deadline_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'RFQ submitted successfully.',
            'rfq' => DB::table('trade_rfqs')->where('id', $id)->first(),
        ], 201);
    }

    public function store_quote(Request $request, $id)
    {
        if (! Schema::hasTable('trade_rfqs') || ! Schema::hasTable('trade_rfq_quotes')) {
            return response()->json(['message' => 'RFQ tables not initialized yet.'], 503);
        }

        $rfq = DB::table('trade_rfqs')->where('id', $id)->first();
        if (! $rfq) {
            return response()->json(['message' => 'RFQ not found.'], 404);
        }

        $data = $request->validate([
            'supplier_name' => 'required|string|max:150',
            'supplier_phone' => 'required|string|max:40',
            'store_id' => 'nullable|integer',
            'quoted_unit_price_ghs' => 'required|numeric|min:0.01',
            'incoterm' => 'nullable|in:EXW,FOB,CIF,DDP',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
            'compliance_docs' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $quoteId = DB::table('trade_rfq_quotes')->insertGetId([
            'trade_rfq_id' => $rfq->id,
            'store_id' => $data['store_id'] ?? null,
            'supplier_name' => trim($data['supplier_name']),
            'supplier_phone' => trim($data['supplier_phone']),
            'quoted_unit_price_ghs' => round((float) $data['quoted_unit_price_ghs'], 2),
            'incoterm' => strtoupper($data['incoterm'] ?? $rfq->incoterm),
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 7),
            'compliance_docs' => $data['compliance_docs'] ?? 'AfCFTA CoO, Phytosanitary',
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($rfq->status === 'open') {
            DB::table('trade_rfqs')->where('id', $rfq->id)->update([
                'status' => 'quoted',
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Quote submitted successfully.',
            'quote' => DB::table('trade_rfq_quotes')->where('id', $quoteId)->first(),
        ], 201);
    }

    public function accept_quote(Request $request, $id, $quoteId)
    {
        if (! Schema::hasTable('trade_rfqs') || ! Schema::hasTable('trade_rfq_quotes')) {
            return response()->json(['message' => 'RFQ tables not initialized yet.'], 503);
        }

        $rfq = DB::table('trade_rfqs')->where('id', (int) $id)->first();
        if (! $rfq) {
            return response()->json(['message' => 'RFQ not found.'], 404);
        }

        if (($rfq->status ?? 'open') === 'awarded') {
            return response()->json(['message' => 'This RFQ has already been awarded.'], 422);
        }

        $quote = DB::table('trade_rfq_quotes')
            ->where('id', (int) $quoteId)
            ->where('trade_rfq_id', $rfq->id)
            ->first();

        if (! $quote) {
            return response()->json(['message' => 'Quote not found for this RFQ.'], 404);
        }

        DB::transaction(function () use ($rfq, $quote) {
            $rfqUpdate = [
                'status' => 'awarded',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('trade_rfqs', 'accepted_quote_id')) {
                $rfqUpdate['accepted_quote_id'] = $quote->id;
            }
            if (Schema::hasColumn('trade_rfqs', 'awarded_at')) {
                $rfqUpdate['awarded_at'] = now();
            }

            DB::table('trade_rfqs')->where('id', $rfq->id)->update($rfqUpdate);

            if (Schema::hasColumn('trade_rfq_quotes', 'status')) {
                DB::table('trade_rfq_quotes')
                    ->where('trade_rfq_id', $rfq->id)
                    ->where('id', '!=', $quote->id)
                    ->update(['status' => 'declined', 'updated_at' => now()]);

                DB::table('trade_rfq_quotes')
                    ->where('id', $quote->id)
                    ->update(['status' => 'accepted', 'updated_at' => now()]);
            }
        });

        $totalContract = round((float) $quote->quoted_unit_price_ghs * (float) $rfq->quantity, 2);
        $depositDue = round($totalContract * 0.30, 2);

        return response()->json([
            'message' => 'Quote accepted! Contract awarded with Trade Assurance protection.',
            'rfq_id' => $rfq->id,
            'quote_id' => $quote->id,
            'supplier_name' => $quote->supplier_name,
            'total_contract_ghs' => $totalContract,
            'deposit_due_ghs' => $depositDue,
            'trade_assurance' => (bool) ($rfq->requires_insurance ?? true),
        ], 200);
    }

    public function fx_rates()
    {
        $rates = Cache::remember('trade_fx_rates_ghs_v1', now()->addHours(6), function () {
            $fallback = [
                'GHS' => 1.0,
                'USD' => 0.068,
                'NGN' => 108.0,
                'XOF' => 41.5,
                'KES' => 8.8,
                'ZAR' => 1.24,
            ];

            try {
                $response = Http::timeout(4)->get('https://open.er-api.com/v6/latest/GHS');
                if ($response->successful()) {
                    $apiRates = $response->json('rates', []);
                    if (is_array($apiRates) && ! empty($apiRates['USD'])) {
                        return [
                            'GHS' => 1.0,
                            'USD' => round((float) $apiRates['USD'], 4),
                            'NGN' => round((float) ($apiRates['NGN'] ?? $fallback['NGN']), 2),
                            'XOF' => round((float) ($apiRates['XOF'] ?? $fallback['XOF']), 2),
                            'KES' => round((float) ($apiRates['KES'] ?? $fallback['KES']), 2),
                            'ZAR' => round((float) ($apiRates['ZAR'] ?? $fallback['ZAR']), 4),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Fall back to baseline rates when external FX feed is unavailable
            }

            return $fallback;
        });

        return response()->json([
            'base' => 'GHS',
            'rates' => $rates,
            'symbols' => [
                'GHS' => '₵',
                'USD' => '$',
                'NGN' => '₦',
                'XOF' => 'CFA ',
                'KES' => 'KSh ',
                'ZAR' => 'R',
            ],
        ], 200);
    }

    public function agro_prices(Request $request)
    {
        $category = $request->query('category');
        $hub = $request->query('market_hub');
        $search = trim((string) $request->query('search', ''));

        $allForFilters = \Illuminate\Support\Facades\Cache::remember(
            'agro_market_prices_active_v1',
            now()->addMinutes(2),
            function () {
                if (! Schema::hasTable('agro_market_prices')) {
                    return collect();
                }
                return DB::table('agro_market_prices')
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();
            }
        );

        $prices = $allForFilters->filter(function ($row) use ($category, $hub, $search) {
            if ($category && $category !== 'all' && ($row->category ?? '') !== $category) {
                return false;
            }
            if ($hub && $hub !== 'all' && ($row->market_hub ?? '') !== $hub) {
                return false;
            }
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $hay = mb_strtolower(($row->commodity ?? '') . ' ' . ($row->market_hub ?? '') . ' ' . ($row->source_platform ?? ''));
                if (! str_contains($hay, $needle)) {
                    return false;
                }
            }
            return true;
        });

        return response()->json([
            'prices' => $prices->values(),
            'categories' => $allForFilters->pluck('category')->unique()->values(),
            'hubs' => $allForFilters->pluck('market_hub')->unique()->values(),
            'sources' => $allForFilters->pluck('source_platform')->filter()->unique()->values(),
            'currency' => 'GHS',
            'currency_symbol' => '₵',
            'updated_at' => $allForFilters->max('recorded_at') ?? now()->toIso8601String(),
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

    protected function buildOrderEsperWorksContext($id): ?array
    {
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) {
            return null;
        }
        $store = $order->store_id ? DB::table('stores')->where('id', $order->store_id)->first() : null;
        $details = DB::table('order_details')->where('order_id', $order->id)->get();
        $address = $order->delivery_address ? json_decode($order->delivery_address, true) : [];

        $unitLabels = [
            'kg' => 'Kilogram (kg)',
            'bag_50kg' => '50kg Bag',
            'bag_100kg' => '100kg Bag',
            'crate' => 'Crate',
            'bunch' => 'Bunch',
            'ton' => 'Metric Ton',
            'box' => 'Export Box',
        ];

        $lineItems = [];
        $totalWeightKg = 0.0;
        foreach ($details as $d) {
            $itemInfo = $d->item_details ? json_decode($d->item_details, true) : [];
            $agroUnit = $itemInfo['agro_unit'] ?? null;
            $unitLabel = $agroUnit ? ($unitLabels[$agroUnit] ?? $agroUnit) : ($itemInfo['unit_type'] ?? 'Unit');
            $grade = $itemInfo['crop_grade'] ?? 'Standard Grade';
            $unitKg = (float) ($itemInfo['unit_weight_kg'] ?? 0);
            $qty = (int) ($d->quantity ?? 1);
            $lineKg = round($unitKg * $qty, 2);
            $totalWeightKg += $lineKg;
            $price = (float) ($d->price ?? 0);

            $lineItems[] = [
                'name' => $itemInfo['name'] ?? 'Agro Commodity',
                'quantity' => $qty,
                'unit' => $unitLabel,
                'crop_grade' => $grade,
                'unit_weight_kg' => $unitKg,
                'line_weight_kg' => $lineKg,
                'unit_price' => round($price, 2),
                'line_total' => round($price * $qty, 2),
            ];
        }

        return [
            'order' => $order,
            'store' => $store,
            'address' => $address,
            'line_items' => $lineItems,
            'total_weight_kg' => round($totalWeightKg, 2),
        ];
    }

    public function esperworks_invoice(Request $request, $id)
    {
        $ctx = $this->buildOrderEsperWorksContext($id);
        if (!$ctx) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order = $ctx['order'];
        $store = $ctx['store'];
        $address = $ctx['address'];
        $lineItems = $ctx['line_items'];
        $totalWeightKg = $ctx['total_weight_kg'];

        $baseUrl = rtrim(env('ESPERWORKS_API_URL', 'https://api.tryesperworks.com/api/v1'), '/');
        $apiKey = env('ESPERWORKS_API_KEY') ?: (Schema::hasTable('business_settings')
            ? DB::table('business_settings')->where('key', 'esperworks_api_key')->value('value')
            : null);

        $invoiceNumber = 'EW-ADU-INV-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
        $payload = [
            'reference' => $invoiceNumber,
            'currency' => 'GHS',
            'issue_date' => now()->toDateString(),
            'client' => [
                'name' => $address['contact_person_name'] ?? 'Aduanefie Buyer',
                'phone' => $address['contact_person_number'] ?? '',
                'email' => $address['contact_person_email'] ?? '',
                'address' => $address['address'] ?? '',
            ],
            'supplier' => [
                'name' => $store->name ?? 'Aduanefie Verified Farm',
                'phone' => $store->phone ?? '',
                'address' => $store->address ?? 'Ghana',
                'tin' => $store->tin ?? '',
            ],
            'items' => $lineItems,
            'delivery_charge' => round((float) ($order->delivery_charge ?? 0), 2),
            'insurance_fee' => round((float) ($order->insurance_fee ?? 0), 2),
            'total_amount' => round((float) ($order->order_amount ?? 0), 2),
            'total_weight_kg' => $totalWeightKg,
            'provider' => 'EsperWorks (tryesperworks.com)',
        ];

        $remoteUrl = null;
        $remoteStatus = 'local_generated';
        if (!empty($apiKey)) {
            try {
                $res = Http::timeout(8)
                    ->withToken($apiKey)
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->post("{$baseUrl}/invoices", $payload);

                if ($res->successful()) {
                    $body = $res->json();
                    $remoteUrl = $body['invoice_url'] ?? $body['data']['invoice_url'] ?? $body['url'] ?? null;
                    $remoteStatus = 'synced_with_esperworks';
                }
            } catch (\Throwable $e) {
                $remoteStatus = 'esperworks_fallback';
            }
        }

        return response()->json([
            'invoice_number' => $invoiceNumber,
            'status' => $remoteStatus,
            'esperworks_url' => $remoteUrl,
            'invoice' => $payload,
        ], 200);
    }

    public function esperworks_contract(Request $request, $id)
    {
        $ctx = $this->buildOrderEsperWorksContext($id);
        if (!$ctx) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order = $ctx['order'];
        $store = $ctx['store'];
        $address = $ctx['address'];
        $lineItems = $ctx['line_items'];
        $totalWeightKg = $ctx['total_weight_kg'];

        $baseUrl = rtrim(env('ESPERWORKS_API_URL', 'https://api.tryesperworks.com/api/v1'), '/');
        $apiKey = env('ESPERWORKS_API_KEY') ?: (Schema::hasTable('business_settings')
            ? DB::table('business_settings')->where('key', 'esperworks_api_key')->value('value')
            : null);

        $contractNumber = 'EW-ADU-CTR-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
        $payload = [
            'contract_number' => $contractNumber,
            'title' => 'Agro Commodity Supply, Grading & Escrow Assurance Agreement',
            'effective_date' => now()->toDateString(),
            'currency' => 'GHS',
            'buyer' => [
                'name' => $address['contact_person_name'] ?? 'Aduanefie Buyer',
                'phone' => $address['contact_person_number'] ?? '',
                'email' => $address['contact_person_email'] ?? '',
                'delivery_address' => $address['address'] ?? '',
            ],
            'supplier' => [
                'name' => $store->name ?? 'Aduanefie Verified Farm',
                'phone' => $store->phone ?? '',
                'address' => $store->address ?? 'Ghana',
                'tin' => $store->tin ?? '',
            ],
            'items' => $lineItems,
            'total_weight_kg' => $totalWeightKg,
            'total_amount' => round((float) ($order->order_amount ?? 0), 2),
            'is_insured' => (bool) ($order->is_insured ?? false),
            'insurance_fee' => round((float) ($order->insurance_fee ?? 0), 2),
            'terms' => [
                'quality_inspection' => 'Produce must match the stated Crop Grade and Agro Unit weight at farm-gate dispatch and buyer delivery.',
                'escrow_release' => 'Funds are held in Aduanefie Trade Assurance and released upon confirmed delivery or within 24 hours of waybill sign-off.',
                'transit_coverage' => ((bool) ($order->is_insured ?? false))
                    ? 'Covered under Trade & Crop Insurance against verified transit spoilage or non-delivery.'
                    : 'Standard carrier liability applies.',
            ],
            'provider' => 'EsperWorks e-Sign (tryesperworks.com)',
        ];

        $remoteSignUrl = null;
        $remoteStatus = 'ready_for_signature';
        if (!empty($apiKey)) {
            try {
                $res = Http::timeout(8)
                    ->withToken($apiKey)
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->post("{$baseUrl}/contracts", $payload);

                if ($res->successful()) {
                    $body = $res->json();
                    $remoteSignUrl = $body['signing_url'] ?? $body['data']['signing_url'] ?? $body['url'] ?? null;
                    $remoteStatus = 'synced_with_esperworks';
                }
            } catch (\Throwable $e) {
                $remoteStatus = 'esperworks_fallback';
            }
        }

        return response()->json([
            'contract_number' => $contractNumber,
            'status' => $remoteStatus,
            'esperworks_signing_url' => $remoteSignUrl,
            'contract' => $payload,
        ], 200);
    }
}
