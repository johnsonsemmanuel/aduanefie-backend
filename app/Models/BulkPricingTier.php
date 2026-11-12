<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkPricingTier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'discount_percent' => 'float',
        'price_override' => 'float',
        'status' => 'string',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the effective price for a given quantity.
     */
    public static function getEffectivePrice(Item $item, int $quantity): array
    {
        $tier = self::where('item_id', $item->id)
            ->where('status', 'active')
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderBy('min_quantity', 'desc')
            ->first();

        if (!$tier) {
            return [
                'price' => $item->price,
                'discount_percent' => 0,
                'tier' => null,
                'effective_price' => $item->price,
            ];
        }

        $basePrice = $item->price;
        $discountPercent = $tier->discount_percent;
        $priceOverride = $tier->price_override;

        if ($priceOverride) {
            $effectivePrice = $priceOverride;
            $discountPercent = $basePrice > 0 ? round((1 - $priceOverride / $basePrice) * 100, 2) : 0;
        } else {
            $effectivePrice = round($basePrice * (1 - $discountPercent / 100), 2);
        }

        return [
            'price' => $basePrice,
            'discount_percent' => $discountPercent,
            'tier' => $tier,
            'effective_price' => $effectivePrice,
            'min_quantity' => $tier->min_quantity,
        ];
    }

    public static function getAllTiers(Item $item)
    {
        return self::where('item_id', $item->id)
            ->where('status', 'active')
            ->orderBy('min_quantity', 'asc')
            ->get();
    }
}
