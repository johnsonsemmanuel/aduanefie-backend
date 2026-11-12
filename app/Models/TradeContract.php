<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TradeContract extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'contract_amount' => 'float',
        'escrow_amount' => 'float',
        'payment_status' => 'string',
        'delivery_status' => 'string',
        'delivery_confirmed_at' => 'datetime',
        'docs_verified_at' => 'datetime',
        'funds_released_at' => 'datetime',
    ];

    public function tradeRequest(): BelongsTo
    {
        return $this->belongsTo(TradeRequest::class, 'trade_request_id');
    }

    public function tradeQuote(): BelongsTo
    {
        return $this->belongsTo(TradeRequestQuote::class, 'trade_quote_id');
    }

    public function assurance(): BelongsTo
    {
        return $this->hasOne(TradeAssurance::class, 'trade_contract_id');
    }

    public function scopePendingDeposit($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeDeposited($query)
    {
        return $query->where('payment_status', 'deposited');
    }

    public function scopeReleased($query)
    {
        return $query->where('payment_status', 'released');
    }

    public function canReleaseFunds(): bool
    {
        return $this->payment_status === 'deposited'
            && $this->delivery_status === 'docs_verified';
    }

    public function getStatusLabel(): string
    {
        $labels = [
            'pending' => 'Awaiting Deposit',
            'deposited' => 'Funds in Escrow',
            'released' => 'Funds Released',
            'refunded' => 'Refunded',
            'disputed' => 'Disputed',
        ];
        return $labels[$this->payment_status] ?? $this->payment_status;
    }
}
