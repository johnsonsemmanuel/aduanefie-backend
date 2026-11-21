<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail for marketer programme actions.
 *
 * Every money-adjacent or attribution change made by a super admin through
 * the /admin/marketer screens is recorded here: payout approval/rejection,
 * payout recording as paid, commission rate and bounty changes, store
 * attribution. The row itself cannot be edited after creation.
 */
class MarketerAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'subject_id',
        'subject_type',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
