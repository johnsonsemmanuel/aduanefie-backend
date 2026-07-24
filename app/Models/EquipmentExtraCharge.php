<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentExtraCharge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'charge_type',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(EquipmentBooking::class, 'booking_id');
    }
}
