<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentBooking extends Model
{
    protected $fillable = [
        'item_id',
        'customer_id',
        'store_id',
        'order_id',
        'start_date',
        'end_date',
        'duration_type',
        'duration_value',
        'total_amount',
        'security_deposit',
        'operator_included',
        'operator_fee',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'duration_value' => 'integer',
        'total_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'operator_fee' => 'decimal:2',
        'operator_included' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function conditionReports(): HasMany
    {
        return $this->hasMany(EquipmentConditionReport::class, 'booking_id');
    }

    public function extraCharges(): HasMany
    {
        return $this->hasMany(EquipmentExtraCharge::class, 'booking_id');
    }
}
