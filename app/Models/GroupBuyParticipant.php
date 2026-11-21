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

    /**
     * Defence in depth: these fields are private to the member and must never
     * appear in a serialised participant, whichever endpoint returns one. The
     * membership-gated member list uses an explicit allowlist that excludes
     * them; hiding them here prevents an accidental future `toArray()` from
     * leaking addresses, coordinates or internal identifiers.
     */
    protected $hidden = [
        'user_id',
        'guest_id',
        'delivery_address',
        'gps_lat',
        'gps_lng',
        'consent_accepted_at',
        'notification_last_sent_at',
        'released_at',
    ];

    protected $casts = [
        'group_buy_id' => 'integer',
        'user_id' => 'integer',
        'share_units' => 'integer',
        'item_share_ghs' => 'float',
        'delivery_charge_ghs' => 'float',
        'delivery_address' => 'array',
        'joined_at' => 'datetime',
        'consent_accepted_at' => 'datetime',
        'released_at' => 'datetime',
        'notification_last_sent_at' => 'datetime',
        'gps_lat' => 'float',
        'gps_lng' => 'float',
    ];

    public function groupBuy(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this member has accepted the group consent dialogue.
     */
    public function hasConsented(): bool
    {
        return $this->consent_accepted_at !== null;
    }

    /**
     * Whether this member still owes payment.
     */
    public function isUnpaid(): bool
    {
        return $this->payment_status === self::PAYMENT_NOT_CHARGED;
    }

    /**
     * Google Maps link for the delivery address, if coordinates are present.
     */
    public function gpsLink(): ?string
    {
        if ($this->gps_lat && $this->gps_lng) {
            return 'https://www.google.com/maps/search/?api=1&query=' . $this->gps_lat . ',' . $this->gps_lng;
        }
        return null;
    }
}
