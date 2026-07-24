<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentConditionReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'report_type',
        'reported_by',
        'condition_rating',
        'notes',
        'images',
    ];

    protected $casts = [
        'condition_rating' => 'integer',
        'images' => 'array',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(EquipmentBooking::class, 'booking_id');
    }
}
