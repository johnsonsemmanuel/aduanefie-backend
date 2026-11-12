<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_method_id',
        'origin_country',
        'destination_country',
        'min_weight',
        'max_weight',
        'base_rate',
        'per_kg_rate',
        'min_days',
        'max_days',
        'currency_code',
        'cod_allowed',
        'is_active',
    ];

    protected $casts = [
        'shipping_method_id' => 'integer',
        'min_weight' => 'float',
        'max_weight' => 'float',
        'base_rate' => 'float',
        'per_kg_rate' => 'float',
        'min_days' => 'integer',
        'max_days' => 'integer',
        'cod_allowed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function method()
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeLane($query, string $origin, string $destination)
    {
        return $query->where('origin_country', $origin)->where('destination_country', $destination);
    }

    /**
     * Shipping cost for a given total weight, in this rate's currency.
     */
    public function costForWeight(float $weight): float
    {
        $billable = max(0, $weight);

        return round((float) $this->base_rate + ($billable * (float) $this->per_kg_rate), 3);
    }
}
