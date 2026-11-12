<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'effective_at',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_at' => 'datetime',
    ];

    /**
     * Convert an amount between currencies. Returns null when no rate is
     * configured — callers must treat that as "cannot quote" rather than
     * silently charging the wrong currency.
     */
    public static function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rate = static::where('base_currency', $from)
            ->where('quote_currency', $to)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if (! $rate || (float) $rate->rate <= 0) {
            return null;
        }

        return round($amount * (float) $rate->rate, 3);
    }
}
