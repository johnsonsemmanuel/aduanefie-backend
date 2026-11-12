<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeAssurance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'inspection_date' => 'date',
        'inspection_fee' => 'float',
        'certificate_expiry' => 'date',
        'status' => 'string',
    ];

    public function tradeContract(): BelongsTo
    {
        return $this->belongsTo(TradeContract::class, 'trade_contract_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePassed($query)
    {
        return $query->where('status', 'passed');
    }

    public function getBadgeLabel(): string
    {
        $labels = [
            'pending' => 'Assurance Pending',
            'inspected' => 'Under Inspection',
            'passed' => 'Trade Assurance Verified',
            'failed' => 'Assurance Failed',
            'waived' => 'Assurance Waived',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg--warning text-dark',
            'inspected' => 'bg--info text-dark',
            'passed' => 'bg--success text-white',
            'failed' => 'bg--danger text-white',
            'waived' => 'bg--secondary text-white',
        ];
        return $classes[$this->status] ?? 'bg--secondary text-white';
    }

    public function canReleaseWithAssurance(): bool
    {
        return $this->status === 'passed';
    }
}
