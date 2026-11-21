<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupBuy extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected $casts = [
        'module_id' => 'integer',
        'store_id' => 'integer',
        'item_id' => 'integer',
        'organizer_id' => 'integer',
        'target_value_ghs' => 'float',
        'farmer_price_ghs' => 'float',
        'target_units' => 'integer',
        'share_units' => 'integer',
        'member_limit' => 'integer',
        'committed_count' => 'integer',
        'is_cross_border' => 'boolean',
        'closes_at' => 'datetime',
        'locked_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'escrow_released_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GroupBuyParticipant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GroupBuyEvent::class)->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupBuyMessage::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN
            && (! $this->closes_at || $this->closes_at->isFuture())
            && $this->committed_count < $this->member_limit;
    }

    /**
     * How many members have paid (checked out their share).
     */
    public function paidCount(): int
    {
        return (int) $this->participants()
            ->whereIn('payment_status', ['paid', 'ordered'])
            ->count();
    }

    /**
     * How many members still owe payment.
     */
    public function unpaidCount(): int
    {
        return (int) $this->participants()
            ->where('payment_status', GroupBuyParticipant::PAYMENT_NOT_CHARGED)
            ->count();
    }

    /**
     * Whether every member has paid their share.
     */
    public function isFullyPaid(): bool
    {
        return $this->unpaidCount() === 0;
    }

    /**
     * Whether escrow funds have been released to the store.
     */
    public function escrowReleased(): bool
    {
        return $this->escrow_status === 'released';
    }
}
