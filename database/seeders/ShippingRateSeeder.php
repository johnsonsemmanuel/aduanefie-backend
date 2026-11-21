<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

/**
 * Reference lane prices for the Ghana/Nigeria/Togo pilot. These are operator-
 * reviewed baselines, not live carrier quotes. The UI surfaces the provenance
 * and warns that the figures are indicative until an admin updates them.
 */
class ShippingRateSeeder extends Seeder
{
    public function run()
    {
        $lanes = [
            // Shorter ECOWAS corridors.
            ['GH', 'TG', 'road_freight', 28, 0.55, 2, 4],
            ['GH', 'TG', 'courier', 14, 1.80, 1, 3],
            ['TG', 'GH', 'road_freight', 28, 0.55, 2, 4],
            ['TG', 'GH', 'courier', 14, 1.80, 1, 3],
            ['TG', 'CI', 'road_freight', 24, 0.50, 2, 4],
            ['CI', 'TG', 'road_freight', 24, 0.50, 2, 4],
            ['NG', 'TG', 'road_freight', 42, 0.72, 3, 6],
            ['TG', 'NG', 'road_freight', 42, 0.72, 3, 6],

            // Main Ghana corridors.
            ['GH', 'NG', 'road_freight', 55, 0.85, 4, 8],
            ['GH', 'NG', 'courier', 24, 2.40, 2, 5],
            ['NG', 'GH', 'road_freight', 55, 0.85, 4, 8],
            ['NG', 'GH', 'courier', 24, 2.40, 2, 5],
            ['GH', 'CI', 'road_freight', 34, 0.62, 3, 6],
            ['GH', 'CI', 'courier', 16, 1.95, 2, 4],
            ['CI', 'GH', 'road_freight', 34, 0.62, 3, 6],
            ['CI', 'GH', 'courier', 16, 1.95, 2, 4],
            ['GH', 'BF', 'road_freight', 46, 0.78, 4, 7],
            ['GH', 'BF', 'courier', 20, 2.10, 2, 5],
            ['BF', 'GH', 'road_freight', 46, 0.78, 4, 7],
            ['BF', 'GH', 'courier', 20, 2.10, 2, 5],
            ['GH', 'SN', 'road_freight', 72, 0.95, 5, 9],
            ['GH', 'SN', 'courier', 28, 2.75, 3, 6],
            ['SN', 'GH', 'road_freight', 72, 0.95, 5, 9],
            ['SN', 'GH', 'courier', 28, 2.75, 3, 6],
            ['GH', 'BJ', 'road_freight', 52, 0.82, 4, 8],
            ['GH', 'BJ', 'courier', 22, 2.25, 2, 5],
            ['GH', 'ML', 'road_freight', 66, 0.90, 5, 9],
            ['GH', 'ML', 'courier', 26, 2.60, 3, 6],
        ];

        foreach ($lanes as [$origin, $destination, $methodCode, $base, $perKg, $minDays, $maxDays]) {
            $methodId = ShippingMethod::where('code', $methodCode)->value('id');
            if (! $methodId) {
                continue;
            }

            $isCourier = $methodCode === 'courier';
            ShippingRate::updateOrInsert(
                [
                    'shipping_method_id' => (int) $methodId,
                    'origin_country' => $origin,
                    'destination_country' => $destination,
                    'min_weight' => 0,
                ],
                [
                    'max_weight' => $isCourier ? 5 : 30,
                    'base_rate' => $base,
                    'per_kg_rate' => $perKg,
                    'min_days' => $minDays,
                    'max_days' => $maxDays,
                    'currency_code' => 'USD',
                    'source' => 'manual_baseline',
                    'effective_at' => null,
                    'cod_allowed' => (int) ! $isCourier,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
