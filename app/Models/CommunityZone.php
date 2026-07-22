<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityZone extends Model
{
    protected $fillable = ['zone_id', 'name', 'region', 'description', 'status'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
