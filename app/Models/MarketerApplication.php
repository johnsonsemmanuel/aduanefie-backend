<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'phone_verified',
        'phone_verification_id',
        'region',
        'district',
        'momo_network',
        'experience_notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'created_admin_id',
        'rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function createdAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_admin_id');
    }
}
