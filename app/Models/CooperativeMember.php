<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CooperativeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'farmer_id',
        'join_date',
        'shares_owned',
        'role',
        'status',
    ];

    protected $casts = [
        'shares_owned' => 'integer',
        'join_date' => 'date',
    ];

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}
