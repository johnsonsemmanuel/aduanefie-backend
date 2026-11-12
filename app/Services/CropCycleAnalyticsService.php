<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Computes harvest readiness, remaining days, and crop cultivation progress
 * for agricultural products so buyers can distinguish active farming vs ready
 * harvests and book ahead (pre-order) before harvest day.
 */
class CropCycleAnalyticsService
{
    /**
     * Typical days from planting to first harvest in West / Sub-Saharan Africa.
     */
    private const DEFAULT_CYCLE_DAYS = [
        'lettuce' => 45,
        'amaranth' => 35,
        'ayoyo' => 40,
        'spinach' => 45,
        'okra' => 55,
        'okro' => 55,
        'cucumber' => 55,
        'cabbage' => 75,
        'tomato' => 75,
        'pepper' => 80,
        'chili' => 80,
        'watermelon' => 80,
        'garden egg' => 85,
        'eggplant' => 85,
        'cowpea' => 70,
        'beans' => 75,
        'onion' => 100,
        'maize' => 105,
        'corn' => 105,
        'groundnut' => 110,
        'peanut' => 110,
        'rice' => 120,
        'soya' => 110,
        'soybean' => 110,
        'carrot' => 75,
        'ginger' => 240,
        'turmeric' => 240,
        'yam' => 240,
        'cassava' => 270,
        'plantain' => 300,
        'pineapple' => 420,
    ];

    public static function estimateCycleDays(?string $cropName, ?int $explicitDays = null): int
    {
        if ($explicitDays && $explicitDays > 0) {
            return $explicitDays;
        }

        $normalized = strtolower((string) $cropName);
        foreach (self::DEFAULT_CYCLE_DAYS as $keyword => $days) {
            if ($normalized !== '' && str_contains($normalized, $keyword)) {
                return $days;
            }
        }

        return 75;
    }

    public static function analyze($item): array
    {
        $rawStatus = strtolower((string) ($item->farming_status ?? 'in_stock'));
        if (! in_array($rawStatus, ['farming', 'harvest_ready', 'in_stock'], true)) {
            $rawStatus = 'in_stock';
        }

        $today = Carbon::today();
        $plantingDate = ! empty($item->planting_date) ? Carbon::parse($item->planting_date)->startOfDay() : null;
        $cycleDays = isset($item->crop_cycle_days) && (int) $item->crop_cycle_days > 0
            ? (int) $item->crop_cycle_days
            : ($plantingDate ? self::estimateCycleDays($item->name ?? null) : null);

        $expectedHarvest = null;
        if (! empty($item->expected_harvest_date)) {
            $expectedHarvest = Carbon::parse($item->expected_harvest_date)->startOfDay();
        } elseif (! empty($item->harvest_date)) {
            $expectedHarvest = Carbon::parse($item->harvest_date)->startOfDay();
        } elseif ($plantingDate && $cycleDays) {
            $expectedHarvest = $plantingDate->copy()->addDays($cycleDays);
        }

        $daysToHarvest = null;
        $progressPct = null;

        if ($expectedHarvest) {
            $diff = $today->diffInDays($expectedHarvest, false);
            $daysToHarvest = max(0, (int) $diff);

            if ($plantingDate) {
                $totalSpan = max(1, (int) $plantingDate->diffInDays($expectedHarvest, false));
                $elapsed = max(0, (int) $plantingDate->diffInDays($today, false));
                $progressPct = min(100, (int) round(($elapsed / $totalSpan) * 100));
            } elseif ($cycleDays) {
                $elapsed = max(0, $cycleDays - $daysToHarvest);
                $progressPct = min(100, (int) round(($elapsed / max(1, $cycleDays)) * 100));
            } else {
                $progressPct = $daysToHarvest === 0 ? 100 : 50;
            }
        }

        $effectiveStatus = $rawStatus;
        if ($rawStatus === 'farming' && $expectedHarvest && $daysToHarvest === 0) {
            $effectiveStatus = 'harvest_ready';
        } elseif ($rawStatus === 'farming' && $expectedHarvest && $expectedHarvest->lessThanOrEqualTo($today)) {
            $effectiveStatus = 'harvest_ready';
        }

        if ($effectiveStatus !== 'farming' && $progressPct === null && ($rawStatus === 'harvest_ready')) {
            $progressPct = 100;
            $daysToHarvest = 0;
        }

        $preOrderEnabled = (bool) ($item->pre_order_enabled ?? false);
        $preOrderLimit = isset($item->pre_order_limit) ? (int) $item->pre_order_limit : null;
        $canPreOrder = $preOrderEnabled && in_array($effectiveStatus, ['farming', 'harvest_ready'], true);

        return [
            'farming_status' => $rawStatus,
            'effective_farming_status' => $effectiveStatus,
            'planting_date' => $plantingDate?->toDateString(),
            'expected_harvest_date' => $expectedHarvest?->toDateString(),
            'crop_cycle_days' => $cycleDays,
            'days_to_harvest' => $daysToHarvest,
            'cultivation_progress_pct' => $progressPct,
            'pre_order_enabled' => $preOrderEnabled,
            'pre_order_available' => $canPreOrder,
            'pre_order_limit' => $preOrderLimit,
            'status_label' => match ($effectiveStatus) {
                'farming' => $daysToHarvest !== null
                    ? "Farming · Ready in {$daysToHarvest}d"
                    : 'Farming Now',
                'harvest_ready' => 'Harvest Ready',
                default => 'In Stock',
            },
        ];
    }
}
