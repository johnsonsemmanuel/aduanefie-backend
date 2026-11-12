<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeCorridor extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->corridor_name)) {
                $model->corridor_name = ucfirst($model->origin_country) . ' → ' . ucfirst($model->destination_country);
            }
        });
    }

    protected $guarded = ['id'];

    protected $casts = [
        'distance_km' => 'float',
        'transit_days_estimate' => 'integer',
        'trucking_rate_per_ton_usd' => 'float',
        'minimum_charge_usd' => 'float',
        'cold_chain_available' => 'boolean',
        'warehousing_available' => 'boolean',
        'status' => 'string',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBetween($query, $origin, $destination)
    {
        return $query->where('origin_country', $origin)
            ->where('destination_country', $destination);
    }

    public function getFreightLabelAttribute()
    {
        return ucfirst($this->origin_country) . ' → ' . ucfirst($this->destination_country);
    }

    public function estimateFreightCost(float $weightTons): array
    {
        $rate = $this->trucking_rate_per_ton_usd;
        $minimum = $this->minimum_charge_usd;
        $calculated = $weightTons * $rate;
        $total = max($calculated, $minimum);

        return [
            'corridor' => $this->corridor_name,
            'weight_tons' => $weightTons,
            'rate_per_ton_usd' => $rate,
            'minimum_charge_usd' => $minimum,
            'estimated_cost_usd' => round($total, 2),
            'transit_days' => $this->transit_days_estimate,
            'cold_chain_available' => $this->cold_chain_available,
            'warehousing_available' => $this->warehousing_available,
        ];
    }
}
