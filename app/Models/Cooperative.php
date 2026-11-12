<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cooperative extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'zone_id',
        'contact_person',
        'contact_phone',
        'contact_email',
        'address',
        'members_count',
        'share_capital',
        'status',
    ];

    protected $casts = [
        'members_count' => 'integer',
        'share_capital' => 'float',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function members()
    {
        return $this->hasMany(CooperativeMember::class);
    }
}
