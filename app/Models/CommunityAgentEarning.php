<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityAgentEarning extends Model
{
    protected $fillable = ['delivery_man_id', 'order_id', 'amount', 'type', 'note', 'status'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
