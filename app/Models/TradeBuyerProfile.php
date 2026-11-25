<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeBuyerProfile extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';

    protected $guarded = ['id'];

    protected $casts = [
        'target_commodities' => 'array',
        'intended_destinations' => 'array',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Define valid state machine transitions.
     */
    public static function isValidTransition(string $from, string $to): bool
    {
        $transitions = [
            self::STATUS_PENDING => [self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_PENDING],
            self::STATUS_APPROVED => [self::STATUS_REVOKED],
            self::STATUS_REJECTED => [self::STATUS_PENDING], // Resubmission allowed
            self::STATUS_REVOKED => [self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED], // Admin reinstatement
        ];

        return in_array($to, $transitions[$from] ?? [], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TradeBuyerAuditLog::class, 'trade_buyer_profile_id')
            ->orderBy('id', 'desc');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPendingOrReview(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW], true);
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Honest applicant status messaging.
     */
    public function getStatusMessage(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Your trade access application has been received and is queued for initial review by Aduanefie Trade Operations.',
            self::STATUS_UNDER_REVIEW => 'Your trade access application is actively under review by Aduanefie Trade Operations. We verify business standing and destination readiness before unlocking supply.',
            self::STATUS_APPROVED => 'Your cross-border trade sourcing access is approved. You can discover private export supply and declare non-binding interest.',
            self::STATUS_REJECTED => 'Your trade access application was not approved. Reason: ' . ($this->rejection_reason ?: 'Sourcing criteria not met.') . ' You may submit an updated application with revised details.',
            self::STATUS_REVOKED => 'Your trade access has been revoked. Reason: ' . ($this->revocation_reason ?: 'Administrative review.') . ' Please contact trade operations.',
            default => 'Unknown application status.',
        };
    }
}
