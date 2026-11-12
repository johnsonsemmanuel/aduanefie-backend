<?php

namespace Modules\Service\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'category_id',
        'provider_id',
        'title',
        'slug',
        'description',
        'short_description',
        'price',
        'currency',
        'unit',
        'duration_minutes',
        'status',
        'is_featured',
        'thumbnail',
    ];

    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'provider_id' => 'integer',
        'price' => 'float',
        'duration_minutes' => 'integer',
        'status' => 'integer',
        'is_featured' => 'integer',
    ];

    protected $appends = ['thumbnail_full_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function provider()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(ServiceBooking::class, 'service_id');
    }

    public function getThumbnailFullUrlAttribute()
    {
        $value = $this->thumbnail;
        if (!$value) {
            return null;
        }
        return \App\CentralLogics\Helpers::get_full_url('service', $value, 'public');
    }
}
