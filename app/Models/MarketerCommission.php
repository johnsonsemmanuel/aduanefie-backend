<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One earned marketer commission for one delivered order.
 *
 * @property int $id
 * @property int $marketer_id
 * @property int $store_id
 * @property int $order_id
 * @property int|null $order_transaction_id
 * @property string $base_amount
 * @property string $rate
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $eligible_at
 * @property int|null $payout_id
 * @property string|null $void_reason
 */
class MarketerCommission extends Model
{
    /** Earned, holding period not finished (or deliberately on hold). */
    public const STATUS_PENDING = 'pending';

    /** Past the holding period; can be requested in a payout. */
    public const STATUS_ELIGIBLE = 'eligible';

    /** Attached to a payout awaiting admin approval. Not requestable again. */
    public const STATUS_IN_PAYOUT = 'in_payout';

    /** The payout was recorded as paid. */
    public const STATUS_PAID = 'paid';

    /** Order was canceled/refunded/failed. Never payable. */
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'marketer_id',
        'store_id',
        'order_id',
        'order_transaction_id',
        'base_amount',
        'rate',
        'amount',
        'currency',
        'status',
        'eligible_at',
        'payout_id',
        'void_reason',
    ];

    protected $casts = [
        'marketer_id' => 'integer',
        'store_id' => 'integer',
        'order_id' => 'integer',
        'order_transaction_id' => 'integer',
        'base_amount' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'eligible_at' => 'datetime',
        'payout_id' => 'integer',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'marketer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(OrderTransaction::class, 'order_transaction_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketerPayout::class, 'payout_id');
    }

    public function scopeForMarketer($query, int $marketerId)
    {
        return $query->where('marketer_id', $marketerId);
    }

    public function scopePayable($query)
    {
        return $query->where('status', self::STATUS_ELIGIBLE);
    }
}
