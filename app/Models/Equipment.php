<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $fillable = [
        'item_id',
        'hourly_rate',
        'daily_rate',
        'weekly_rate',
        'monthly_rate',
        'security_deposit',
        'min_rental_duration',
        'max_rental_duration',
        'requires_delivery',
        'self_pickup',
        'condition_notes',
        'status',
        'operator_included',
        'operator_fee',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'operator_fee' => 'decimal:2',
        'min_rental_duration' => 'integer',
        'max_rental_duration' => 'integer',
        'requires_delivery' => 'boolean',
        'self_pickup' => 'boolean',
        'operator_included' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EquipmentBooking::class, 'item_id', 'item_id');
    }
}
