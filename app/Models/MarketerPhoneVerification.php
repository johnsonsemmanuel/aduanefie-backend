<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketerPhoneVerification extends Model
{
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'phone',
        'attempts',
        'verified_name',
        'network',
        'verified_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->verified_at !== null && ! empty($this->verified_name);
    }
}
