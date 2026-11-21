<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBuyParticipant extends Model
{
    public const PAYMENT_NOT_CHARGED = 'not_charged';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_RELEASED = 'released';
    public const PAYMENT_REFUNDED = 'refunded';

    public const FULFILLMENT_PENDING = 'pending';
    public const FULFILLMENT_SHIPPED = 'shipped';
    public const FULFILLMENT_DELIVERED = 'delivered';

    protected $guarded = ['id'];

    protected $casts = [
        'group_buy_id' => 'integer',
        'user_id' => 'integer',
        'share_units' => 'integer',
        'item_share_ghs' => 'float',
        'delivery_charge_ghs' => 'float',
        'delivery_address' => 'array',
        'joined_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function groupBuy(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
