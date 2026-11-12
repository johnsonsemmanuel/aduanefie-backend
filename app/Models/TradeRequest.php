<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $fillable = [
        'buyer_id',
        'buyer_type',
        'origin_country',
        'product_id',
        'quantity',
        'unit',
        'destination_country',
        'incoterm',
        'needed_by',
        'notes',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'needed_by' => 'date',
        'status' => 'string',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(TradeRequestQuote::class, 'trade_request_id');
    }

    public function documents()
    {
        return $this->hasMany(TradeDocument::class, 'trade_request_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeQuoted($query)
    {
        return $query->where('status', 'quoted');
    }

    /**
     * Find the trade corridor between origin and destination.
     */
    public function corridor()
    {
        return TradeCorridor::between($this->origin_country, $this->destination_country)->first();
    }

    /**
     * Estimate freight cost for this trade request.
     */
    public function estimateFreight(): ?array
    {
        $corridor = $this->corridor();
        if (!$corridor) {
            return null;
        }
        $weightTons = $this->quantity / 1000; // assume quantity is in kg
        return $corridor->estimateFreightCost($weightTons);
    }

    /**
     * Estimate customs duties for this trade request.
     */
    public function estimateDuties(): ?array
    {
        $product = $this->product;
        if (!$product || !$product->hs_code) {
            return null;
        }

        $valueUsd = $product->price * $this->quantity; // simplified: price * qty in USD
        return CustomsDuty::calculateDuties($valueUsd, $this->destination_country, $product->hs_code);
    }

    /**
     * List required documents based on destination country and HS code.
     */
    public function requiredDocuments(): array
    {
        $dutyEstimate = $this->estimateDuties();
        if ($dutyEstimate) {
            return $dutyEstimate['documents_required'];
        }
        return [];
    }

    /**
     * Check cold chain availability on this corridor.
     */
    public function coldChainAvailable(): bool
    {
        $corridor = $this->corridor();
        return $corridor ? (bool) $corridor->cold_chain_available : false;
    }

    /**
     * Check warehousing availability on this corridor.
     */
    public function warehousingAvailable(): bool
    {
        $corridor = $this->corridor();
        return $corridor ? (bool) $corridor->warehousing_available : false;
    }

    /**
     * Get corridor logistics summary.
     */
    public function logisticsSummary(): ?array
    {
        $corridor = $this->corridor();
        if (!$corridor) {
            return null;
        }

        return [
            'corridor_name' => $corridor->corridor_name,
            'distance_km' => $corridor->distance_km,
            'transit_days_estimate' => $corridor->transit_days_estimate,
            'cold_chain_available' => $corridor->cold_chain_available,
            'warehousing_available' => $corridor->warehousing_available,
            'trucking_rate_per_ton_usd' => $corridor->trucking_rate_per_ton_usd,
            'minimum_charge_usd' => $corridor->minimum_charge_usd,
        ];
    }

    /**
     * Get or create the trade contract for this request.
     */
    public function contract()
    {
        return $this->hasOne(TradeContract::class, 'trade_request_id')->with('assurance');
    }

    /**
     * Create a trade contract from an accepted quote.
     */
    public function createContractFromQuote(): ?TradeContract
    {
        $quote = $this->quotes()->accepted()->first();
        if (!$quote) {
            return null;
        }

        return TradeContract::create([
            'trade_request_id' => $this->id,
            'trade_quote_id' => $quote->id,
            'buyer_id' => $this->buyer_id,
            'buyer_type' => $this->buyer_type,
            'seller_id' => $quote->seller_id,
            'seller_type' => $quote->seller_type,
            'contract_amount' => $quote->quoted_price,
            'currency_code' => $quote->currency_code,
            'escrow_amount' => 0,
            'payment_status' => 'pending',
            'delivery_status' => 'pending',
        ]);
    }
}
