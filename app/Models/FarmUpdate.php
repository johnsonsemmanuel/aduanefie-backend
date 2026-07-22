<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmUpdate extends Model
{
    protected $fillable = ['store_id', 'title', 'description', 'image', 'update_date', 'status'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
