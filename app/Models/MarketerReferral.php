<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerReferral extends Model
{
    protected $fillable = ['marketer_id', 'referred_user_id', 'referred_name', 'referred_phone', 'referred_email', 'status', 'earned_at'];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
