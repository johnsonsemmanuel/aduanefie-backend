<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregationHub extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'store_id',
        'name',
        'hub_code',
        'address',
        'city',
        'region',
        'country_code',
        'latitude',
        'longitude',
        'phone',
        'contact_person',
        'capacity_kg',
        'current_utilization_kg',
        'status',
        'services',
        'notes',
    ];

    protected $casts = [
        'capacity_kg' => 'float',
        'current_utilization_kg' => 'float',
        'services' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNearby($query, float $latitude, float $longitude, float $radiusKm = 50)
    {
        return $query->whereRaw(
            "ACOS(
                COS(RADIANS(?)) * COS(RADIANS(latitude)) *
                COS(RADIANS(longitude) - RADIANS(?)) +
                SIN(RADIANS(?)) * SIN(RADIANS(latitude))
            ) * 6371 <= ?",
            [$latitude, $longitude, $latitude, $radiusKm]
        );
    }

    public function getAvailableCapacityKgAttribute(): float
    {
        return max(0, (float) $this->capacity_kg - (float) $this->current_utilization_kg);
    }

    public function getUtilizationPercentAttribute(): float
    {
        if (!$this->capacity_kg || $this->capacity_kg <= 0) {
            return 0.0;
        }
        return round(($this->current_utilization_kg / $this->capacity_kg) * 100, 1);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            'at_capacity' => 'At Capacity',
            default => ucfirst($this->status),
        };
    }
}
