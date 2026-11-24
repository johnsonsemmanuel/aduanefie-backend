<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionalTourRegistration extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_VISITED = 'visited';
    public const STATUS_ONBOARDED = 'onboarded';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'user_id',
        'tour_id',
        'farmer_name',
        'phone',
        'email',
        'region',
        'community',
        'farm_size_acres',
        'primary_crops',
        'is_cooperative',
        'farmer_count',
        'latitude',
        'longitude',
        'marketer_id',
        'status',
        'checked_in_at',
        'converted_store_id',
        'notes',
    ];

    protected $casts = [
        'farm_size_acres' => 'float',
        'is_cooperative' => 'boolean',
        'farmer_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'checked_in_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(RegionalTour::class, 'tour_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'marketer_id');
    }

    public function convertedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'converted_store_id');
    }
}
