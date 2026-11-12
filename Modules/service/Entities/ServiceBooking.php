<?php

namespace Modules\Service\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBooking extends Model
{
    use HasFactory;

    protected $table = 'service_bookings';

    protected $fillable = [
        'service_id',
        'user_id',
        'guest_id',
        'provider_id',
        'scheduled_at',
        'address',
        'latitude',
        'longitude',
        'notes',
        'status',
        'total_amount',
        'currency',
        'payment_status',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'id' => 'integer',
        'service_id' => 'integer',
        'user_id' => 'integer',
        'guest_id' => 'string',
        'provider_id' => 'integer',
        'scheduled_at' => 'datetime',
        'total_amount' => 'float',
        'status' => 'string',
        'payment_status' => 'string',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function provider()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'provider_id');
    }
}
