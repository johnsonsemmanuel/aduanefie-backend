<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSourcingRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'min_quantity' => 'integer',
        'budget_min' => 'float',
        'budget_max' => 'float',
        'needed_by' => 'date',
        'status' => 'string',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function getStatusLabel(): string
    {
        $labels = [
            'open' => 'Open',
            'matched' => 'Matched with Sellers',
            'completed' => 'Completed',
            'closed' => 'Closed',
        ];
        return $labels[$this->status] ?? $this->status;
    }
}
