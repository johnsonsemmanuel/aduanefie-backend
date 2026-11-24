<?php

namespace App\Services\CrossBorder;

use App\Models\CurrencyRate;
use App\Models\CustomsDuty;
use App\Models\ShippingRate;
use App\Models\Store;
use App\CentralLogics\Helpers;

/**
 * Prices a cross-border basket server-side.
 *
 * Nothing here trusts client-supplied money. Callers resolve the cart into
 * `$lines` from the database first, then this service decides which shipping
 * lanes are actually configured for the origin/destination pair, converts the
 * rate into the platform currency, and estimates duty/VAT/excise.
 *
 * Failure is explicit: if no lane is configured, or a rate's currency has no
 * conversion, the option is not offered. It never falls back to a guess.
 */
class ShippingQuoteService
{
    public const INCOTERMS = ['DDP', 'DAP', 'CIF', 'FOB', 'EXW'];

    public function platformCurrency(): string
    {
        try {
            return strtoupper(Helpers::currency_code() ?: 'GHS');
        } catch (\Throwable $e) {
            return 'GHS';
        }
    }

    public function originCountry(Store $store): string
    {
        if (! empty($store->country_code)) {
            return strtoupper($store->country_code);
        }

        return strtoupper((string) config('country.code', 'GH'));
    }

    public function isCrossBorder(Store $store, ?string $destinationCountry): bool
    {
        if (empty($destinationCountry)) {
            return false;
        }

        return $this->originCountry($store) !== strtoupper($destinationCountry);
    }

    /**
     * Selectable logistics options for a lane, cheapest first.
     *
     * @param  array<int,array{item_id?:int,quantity?:int,unit_price?:float,weight?:float,hs_code?:?string,name?:?string}>  $lines
     * @return array{available:bool,cross_border:bool,origin_country:string,destination_country:string,currency:string,weight:float,options:array,warnings:array}
     */
    public function options(Store $store, string $destinationCountry, array $lines, ?string $currency = null): array
    {
        $origin = $this->originCountry($store);
        $destination = strtoupper($destinationCountry);
        $currency = strtoupper($currency ?: $this->platformCurrency());
        $weight = $this->totalWeight($lines);

        $base = [
            'available' => false,
            'cross_border' => $origin !== $destination,
            'origin_country' => $origin,
            'destination_country' => $destination,
            'currency' => $currency,
            'weight' => round($weight, 3),
            'options' => [],
            'warnings' => [],
        ];

        if ($origin === $destination) {
            return array_merge($base, ['warnings' => ['same_country']]);
        }

        $rates = ShippingRate::active()
            ->with('method')
            ->lane($origin, $destination)
            ->whereHas('method', fn ($query) => $query->where('is_active', 1))
            ->where('min_weight', '<=', $weight)
            ->where(function ($query) use ($weight) {
                $query->whereNull('max_weight')->orWhere('max_weight', '>=', $weight);
            })
            ->get();

        $taxes = $this->taxes($destination, $lines);

        $options = [];
        $warnings = [];

        foreach ($rates as $rate) {
            $rawCost = $rate->costForWeight($weight);
            $converted = CurrencyRate::convert($rawCost, (string) $rate->currency_code, $currency);

            if ($converted === null) {
                // Fail closed: don't show a price we cannot actually charge.
                $warnings[] = 'missing_fx:' . $rate->currency_code . '->' . $currency;
                continue;
            }

            $landedTotal = round($taxes['goods_subtotal'] + $converted + $taxes['duty'] + $taxes['vat'] + $taxes['excise'], 3);

            $options[] = [
                'shipping_rate_id' => (int) $rate->id,
                'shipping_method_id' => (int) $rate->shipping_method_id,
                'code' => $rate->method?->code,
                'name' => $rate->method?->name,
                'carrier' => $rate->method?->carrier,
                'mode' => $rate->method?->mode,
                'description' => $rate->method?->description,
                'amount' => $converted,
                'rate_currency' => $rate->currency_code,
                'currency' => $currency,
                'estimated_days' => [
                    'min' => $rate->min_days,
                    'max' => $rate->max_days,
                ],
                'cod_allowed' => (bool) $rate->cod_allowed,
                'landed_cost' => [
                    'goods' => $taxes['goods_subtotal'],
                    'shipping' => $converted,
                    'duty' => $taxes['duty'],
                    'vat' => $taxes['vat'],
                    'excise' => $taxes['excise'],
                    'total' => $landedTotal,
                ],
            ];
        }

        usort($options, fn ($a, $b) => $a['amount'] <=> $b['amount']);

        return array_merge($base, [
            'available' => count($options) > 0,
            'options' => $options,
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    /**
     * Duty/VAT/excise estimate for consumed goods. VAT is levied on goods plus
     * duty plus excise; shipping is not included in the taxable base (kept
     * simple and consistent across lanes so the tax figure does not change when
     * the shopper switches logistics provider).
     *
     * @param  array<int,array{quantity?:int,unit_price?:float,hs_code?:?string,name?:?string}>  $lines
     * @return array{goods_subtotal:float,duty:float,vat:float,excise:float,total_taxes:float,duty_configured:bool,warnings:array}
     */
    public function taxes(string $destinationCountry, array $lines): array
    {
        $destination = strtoupper($destinationCountry);
        $goodsSubtotal = 0.0;
        $duty = 0.0;
        $vat = 0.0;
        $excise = 0.0;
        $configured = true;
        $warnings = [];

        foreach ($lines as $line) {
            $lineTotal = (float) ($line['unit_price'] ?? 0) * (int) ($line['quantity'] ?? 0);
            $goodsSubtotal += $lineTotal;

            $rule = CustomsDuty::resolve($destination, $line['hs_code'] ?? null);

            if (! $rule) {
                $configured = false;
                $warnings[] = 'no_customs_rule:' . $destination;
                continue;
            }

            $lineDuty = $lineTotal * ((float) $rule->duty_rate_percent / 100);
            $lineExcise = $lineTotal * ((float) $rule->excise_rate_percent / 100);
            $lineVat = ($lineTotal + $lineDuty + $lineExcise) * ((float) $rule->vat_rate_percent / 100);

            $duty += $lineDuty;
            $excise += $lineExcise;
            $vat += $lineVat;
        }

        return [
            'goods_subtotal' => round($goodsSubtotal, 3),
            'duty' => round($duty, 3),
            'vat' => round($vat, 3),
            'excise' => round($excise, 3),
            'total_taxes' => round($duty + $vat + $excise, 3),
            'duty_configured' => $configured,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Resolve the exact rate the shopper picked and re-price it server-side.
     *
     * @return array{available:bool,reason?:string,option?:array,warnings:array}
     */
    public function resolveOption(
        Store $store,
        string $destinationCountry,
        array $lines,
        ?int $shippingRateId = null,
        ?int $shippingMethodId = null,
        ?string $currency = null
    ): array {
        $quoted = $this->options($store, $destinationCountry, $lines, $currency);

        if (! $quoted['available']) {
            return [
                'available' => false,
                'reason' => $quoted['cross_border'] ? 'no_shipping_option_for_lane' : 'same_country',
                'warnings' => $quoted['warnings'],
            ];
        }

        $match = null;
        foreach ($quoted['options'] as $option) {
            if ($shippingRateId && (int) $option['shipping_rate_id'] === (int) $shippingRateId) {
                $match = $option;
                break;
            }
            if (! $shippingRateId && $shippingMethodId && (int) $option['shipping_method_id'] === (int) $shippingMethodId) {
                $match = $option;
                break;
            }
        }

        if (! $match) {
            // A supplied id that isn't on the lane is a rejected quote, not a
            // reason to fall back to the cheapest option.
            return [
                'available' => false,
                'reason' => 'shipping_option_not_available',
                'warnings' => $quoted['warnings'],
            ];
        }

        return [
            'available' => true,
            'option' => $match,
            'warnings' => $quoted['warnings'],
        ];
    }

    public function totalWeight(array $lines): float
    {
        $weight = 0.0;
        foreach ($lines as $line) {
            $weight += ((float) ($line['weight'] ?? 0)) * max(1, (int) ($line['quantity'] ?? 1));
        }

        return $weight;
    }
}
