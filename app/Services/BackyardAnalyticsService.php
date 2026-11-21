<?php

namespace App\Services;

use App\Models\BackyardFarmApplication;
use App\Models\Item;
use App\Models\Store;
use Carbon\Carbon;

/**
 * Backyard farming analytics.
 *
 * Provides yield predictions, harvest forecasts, and crop rotation
 * suggestions so backyard farmers can plan planting cycles and buyers
 * can see when nearby harvests will be ready.
 */
class BackyardAnalyticsService
{
    /**
     * Estimated yield (kg) per square meter for common backyard crops.
     */
    private const YIELD_PER_SQM = [
        'lettuce' => 3.0,
        'amaranth' => 2.5,
        'ayoyo' => 2.0,
        'spinach' => 2.5,
        'okra' => 1.5,
        'okro' => 1.5,
        'cucumber' => 4.0,
        'cabbage' => 3.0,
        'tomato' => 5.0,
        'pepper' => 3.0,
        'chili' => 2.5,
        'watermelon' => 3.5,
        'garden egg' => 4.0,
        'eggplant' => 4.0,
        'cowpea' => 1.2,
        'beans' => 1.5,
        'onion' => 2.0,
        'maize' => 1.0,
        'corn' => 1.0,
        'groundnut' => 0.8,
        'peanut' => 0.8,
        'rice' => 0.5,
        'soya' => 0.8,
        'soybean' => 0.8,
        'carrot' => 2.0,
        'ginger' => 1.5,
        'turmeric' => 1.5,
        'yam' => 6.0,
        'cassava' => 8.0,
        'plantain' => 4.0,
        'pineapple' => 3.0,
    ];

    /**
     * Aggregate analytics for all backyard farms.
     *
     * @return array{
     *   total_farms:int,
     *   active_farms:int,
     *   harvest_ready_products:int,
     *   estimated_monthly_yield_kg:float,
     *   upcoming_harvests:array,
     *   crop_breakdown:array
     * }
     */
    public function aggregate(): array
    {
        $backyardStores = Store::where('is_backyard', true)->where('status', 1)->get();

        $totalFarms = $backyardStores->count();
        $activeFarms = $backyardStores->where('cultivation_status', 'active')->count();

        $items = Item::whereIn('store_id', $backyardStores->pluck('id'))
            ->where('is_approved', 1)
            ->where('status', 1)
            ->get();

        $harvestReady = $items->where('farming_status', 'harvest_ready')->count();

        $estimatedYield = 0.0;
        $cropBreakdown = [];
        $upcomingHarvests = [];

        foreach ($items as $item) {
            $crop = strtolower((string) ($item->name ?? ''));
            $sqm = $this->estimateSqm($item->store);
            $yieldKg = $sqm * (self::YIELD_PER_SQM[$crop] ?? 2.0);
            $estimatedYield += $yieldKg;

            $key = $item->name ?? 'Unknown';
            if (! isset($cropBreakdown[$key])) {
                $cropBreakdown[$key] = [
                    'name' => $key,
                    'product_count' => 0,
                    'estimated_yield_kg' => 0.0,
                ];
            }
            $cropBreakdown[$key]['product_count']++;
            $cropBreakdown[$key]['estimated_yield_kg'] += $yieldKg;

            if ($item->expected_harvest_date) {
                $upcomingHarvests[] = [
                    'product' => $item->name,
                    'store' => $item->store?->name,
                    'expected_harvest_date' => $item->expected_harvest_date,
                    'days_to_harvest' => Carbon::today()->diffInDays($item->expected_harvest_date),
                ];
            }
        }

        usort($upcomingHarvests, fn ($a, $b) => $a['days_to_harvest'] <=> $b['days_to_harvest']);
        $upcomingHarvests = array_slice($upcomingHarvests, 0, 10);

        uasort($cropBreakdown, fn ($a, $b) => $b['estimated_yield_kg'] <=> $a['estimated_yield_kg']);
        $cropBreakdown = array_slice($cropBreakdown, 0, 10);

        return [
            'total_farms' => $totalFarms,
            'active_farms' => $activeFarms,
            'harvest_ready_products' => $harvestReady,
            'estimated_monthly_yield_kg' => round($estimatedYield, 2),
            'upcoming_harvests' => array_values($upcomingHarvests),
            'crop_breakdown' => array_values($cropBreakdown),
        ];
    }

    /**
     * Per-farm analytics: yield prediction and harvest forecast.
     *
     * @return array{
     *   farm_name:string,
     *   size_acres:float,
     *   size_sqm:float,
     *   primary_crops:string|null,
     *   estimated_yield_per_crop:array,
     *   total_estimated_yield_kg:float,
     *   next_harvest_date:Carbon|null,
     *   days_to_harvest:int|null,
     *   crop_rotation_suggestion:string|null
     * }
     */
    public function farmAnalytics(Store $store): array
    {
        $sizeAcres = (float) ($store->farm_size_acres ?? 0);
        $sizeSqm = $sizeAcres * 4046.86;
        $primaryCrops = $store->primary_crops ?? null;

        $cropList = $primaryCrops ? explode(',', $primaryCrops) : [];
        $yieldPerCrop = [];
        $totalYield = 0.0;

        foreach ($cropList as $crop) {
            $crop = trim($crop);
            if ($crop === '') {
                continue;
            }
            $yieldKg = round($sizeSqm * (self::YIELD_PER_SQM[strtolower($crop)] ?? 2.0), 2);
            $yieldPerCrop[$crop] = $yieldKg;
            $totalYield += $yieldKg;
        }

        $nextHarvest = null;
        $daysToHarvest = null;
        $items = Item::where('store_id', $store->id)
            ->where('is_approved', 1)
            ->where('status', 1)
            ->whereNotNull('expected_harvest_date')
            ->orderBy('expected_harvest_date', 'asc')
            ->get();

        foreach ($items as $item) {
            $harvestDate = Carbon::parse($item->expected_harvest_date);
            if ($harvestDate->isToday() || $harvestDate->isFuture()) {
                $nextHarvest = $harvestDate;
                $daysToHarvest = Carbon::today()->diffInDays($harvestDate);
                break;
            }
        }

        $suggestion = $this->suggestCropRotation($primaryCrops, $sizeAcres);

        return [
            'farm_name' => $store->name,
            'size_acres' => $sizeAcres,
            'size_sqm' => round($sizeSqm, 1),
            'primary_crops' => $primaryCrops,
            'estimated_yield_per_crop' => $yieldPerCrop,
            'total_estimated_yield_kg' => round($totalYield, 2),
            'next_harvest_date' => $nextHarvest?->toDateString(),
            'days_to_harvest' => $daysToHarvest,
            'crop_rotation_suggestion' => $suggestion,
        ];
    }

    /**
     * Suggest a crop rotation based on current crops and farm size.
     */
    private function suggestCropRotation(?string $primaryCrops, float $sizeAcres): ?string
    {
        if (!$primaryCrops) {
            return 'Start with fast-growing leafy greens (lettuce, amaranth) to build soil confidence.';
        }

        $crops = array_map('trim', explode(',', $primaryCrops));
        $lowered = array_map('strtolower', $crops);

        $hasLeafy = false;
        $hasFruiting = false;
        $hasRoot = false;
        $hasLegume = false;

        foreach ($lowered as $crop) {
            if (str_contains($crop, ['lettuce', 'spinach', 'amaranth', 'ayoyo'])) {
                $hasLeafy = true;
            } elseif (str_contains($crop, ['tomato', 'pepper', 'okra', 'cucumber', 'eggplant', 'garden egg'])) {
                $hasFruiting = true;
            } elseif (str_contains($crop, ['carrot', 'onion', 'radish', 'beet'])) {
                $hasRoot = true;
            } elseif (str_contains($crop, ['beans', 'cowpea', 'groundnut', 'soya', 'maize'])) {
                $hasLegume = true;
            }
        }

        $suggestions = [];
        if (!$hasLeafy) {
            $suggestions[] = 'Add leafy greens (lettuce, spinach) for quick harvests in 35-45 days.';
        }
        if (!$hasLegume && $sizeAcres < 0.5) {
            $suggestions[] = 'Plant beans or cowpea to fix nitrogen and improve soil health.';
        }
        if (!$hasFruiting) {
            $suggestions[] = 'Consider tomatoes or peppers for higher-value harvests.';
        }
        if ($hasLeafy && !$hasRoot) {
            $suggestions[] = 'Rotate with root crops (carrots, onions) after leafy greens to break pest cycles.';
        }

        if (empty($suggestions)) {
            return 'Your crop mix is well-balanced. Consider succession planting every 2-3 weeks for continuous harvest.';
        }

        return implode(' ', $suggestions);
    }

    /**
     * Estimate plantable square meters from farm size in acres.
     * Assumes 60% of farm area is plantable (paths, borders, etc.).
     */
    private function estimateSqm(?Store $store): float
    {
        $acres = (float) ($store?->farm_size_acres ?? 0.1);
        return $acres * 4046.86 * 0.6;
    }
}
