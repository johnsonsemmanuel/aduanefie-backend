<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketer extends Model
{
    protected $fillable = ['user_id', 'nid_number', 'nid_image', 'referral_code', 'total_earnings', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(MarketerReferral::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(MarketerEarning::class);
    }
}
