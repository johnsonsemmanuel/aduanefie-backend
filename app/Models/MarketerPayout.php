<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A manual mobile-money payout request from a marketer.
 *
 * Requesting a payout does not move money. It reserves the marketer's eligible
 * commissions so they cannot be requested twice, and puts them in front of an
 * admin. Money is only recorded as sent when an admin marks the payout paid
 * with a transfer reference.
 */
class MarketerPayout extends Model
{
    /** Requested by the marketer; commissions are reserved. */
    public const STATUS_REQUESTED = 'requested';

    /** An admin approved it; still not sent. */
    public const STATUS_APPROVED = 'approved';

    /** An admin recorded the transfer. Commissions become paid. */
    public const STATUS_PAID = 'paid';

    /** Refused; the commissions are released back to eligible. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'marketer_id',
        'amount',
        'currency',
        'method',
        'network',
        'account_name',
        'account_number',
        'country',
        'status',
        'approved_by',
        'requested_at',
        'approved_at',
        'paid_at',
        'reference',
        'note',
        'rejection_reason',
    ];

    protected $casts = [
        'marketer_id' => 'integer',
        'amount' => 'float',
        'approved_by' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'marketer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(MarketerCommission::class, 'payout_id');
    }

    public function scopeForMarketer($query, int $marketerId)
    {
        return $query->where('marketer_id', $marketerId);
    }
}
