<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Item;
use App\Models\Store;
use App\Services\CrossBorder\ShippingQuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Read-only cross-border quoting. Order placement lives in OrderController,
 * which calls the same ShippingQuoteService so a quote and a charge can never
 * disagree.
 */
class CrossBorderController extends Controller
{
    public function __construct(private ShippingQuoteService $quotes)
    {
    }

    public function countries()
    {
        $countries = Country::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name', 'currency_code', 'phone_code']);

        return response()->json(['data' => $countries], 200);
    }

    public function shippingOptions(Request $request)
    {
        $validator = $this->validateRequest($request);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $store = Store::find($request->store_id);
        if (! $store) {
            return response()->json(['errors' => [['code' => 'store_id', 'message' => translate('messages.store_not_found')]]], 404);
        }

        $lines = $this->resolveLines($request->input('lines', []));
        if (! empty($lines['error'])) {
            return response()->json(['errors' => [['code' => 'lines', 'message' => $lines['error']]]], 403);
        }

        $quoted = $this->quotes->options($store, $request->destination_country, $lines['lines']);

        return response()->json([
            'data' => array_merge($quoted, [
                'store_id' => (int) $store->id,
                'store_country' => $this->quotes->originCountry($store),
                'incoterms' => ShippingQuoteService::INCOTERMS,
                'default_incoterm' => 'DDP',
            ]),
        ], 200);
    }

    public function quote(Request $request)
    {
        $validator = $this->validateRequest($request);
        $validator->after(function ($validator) use ($request) {
            if ($request->filled('incoterm') && ! in_array(strtoupper($request->incoterm), ShippingQuoteService::INCOTERMS, true)) {
                $validator->errors()->add('incoterm', 'Unsupported incoterm.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $store = Store::find($request->store_id);
        if (! $store) {
            return response()->json(['errors' => [['code' => 'store_id', 'message' => translate('messages.store_not_found')]]], 404);
        }

        $lines = $this->resolveLines($request->input('lines', []));
        if (! empty($lines['error'])) {
            return response()->json(['errors' => [['code' => 'lines', 'message' => $lines['error']]]], 403);
        }

        $incoterm = strtoupper($request->input('incoterm', 'DDP'));
        $resolved = $this->quotes->resolveOption(
            $store,
            $request->destination_country,
            $lines['lines'],
            $request->filled('shipping_rate_id') ? (int) $request->shipping_rate_id : null,
            $request->filled('shipping_method_id') ? (int) $request->shipping_method_id : null
        );

        if (! $resolved['available']) {
            return response()->json([
                'data' => [
                    'available' => false,
                    'reason' => $resolved['reason'] ?? 'unavailable',
                    'warnings' => $resolved['warnings'] ?? [],
                    'origin_country' => $this->quotes->originCountry($store),
                    'destination_country' => strtoupper($request->destination_country),
                    'currency' => $this->quotes->platformCurrency(),
                ],
            ], 200);
        }

        $taxes = $this->quotes->taxes($request->destination_country, $lines['lines']);
        $option = $resolved['option'];

        return response()->json([
            'data' => [
                'available' => true,
                'cross_border' => true,
                'origin_country' => $this->quotes->originCountry($store),
                'destination_country' => strtoupper($request->destination_country),
                'incoterm' => $incoterm,
                'currency' => $option['currency'],
                'weight' => $this->quotes->totalWeight($lines['lines']),
                'selected' => $option,
                'taxes' => $taxes,
                'landed_cost' => $option['landed_cost'],
                'warnings' => array_values(array_unique(array_merge($resolved['warnings'] ?? [], $taxes['warnings']))),
            ],
        ], 200);
    }

    private function validateRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'destination_country' => 'required|string|size:2',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity' => 'required|integer|min:1',
        ]);
    }

    /**
     * Resolve cart lines from the database. Prices, HS codes and names come
     * from the catalogue, never from the request body.
     *
     * @return array{lines?:array,error?:string}
     */
    private function resolveLines(array $requestedLines): array
    {
        $lines = [];

        foreach ($requestedLines as $line) {
            $item = Item::withoutGlobalScopes()->find($line['item_id'] ?? null);

            if (! $item) {
                return ['error' => translate('messages.product_unavailable_warning')];
            }

            $lines[] = [
                'item_id' => (int) $item->id,
                'name' => $item->name,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unit_price' => (float) $item->price,
                // Weight is catalogue data; the request only supplies quantity.
                'weight' => (float) ($item->weight_kg ?? 0),
                'hs_code' => $item->hs_code ?? null,
            ];
        }

        return ['lines' => $lines];
    }
}
