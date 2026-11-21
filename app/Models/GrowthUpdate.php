<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'vendor_id',
        'progress_pct',
        'notes',
        'photos',
        'scheduled_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'progress_pct' => 'integer',
        'scheduled_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
