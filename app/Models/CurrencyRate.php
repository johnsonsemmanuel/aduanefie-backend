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
        'source',
        'effective_at',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_at' => 'datetime',
    ];

    /**
     * Convert an amount between currencies. Returns null when no rate is
     * configured - callers must treat that as "cannot quote" rather than
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

    /**
     * Provenance for the exact row used by convert(). Displayed in quotes so a
     * manual baseline is never mistaken for a live market rate.
     */
    public static function provenance(string $from, string $to): ?array
    {
        $rate = static::where('base_currency', strtoupper($from))
            ->where('quote_currency', strtoupper($to))
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if (! $rate) {
            return null;
        }

        return [
            'source' => $rate->source,
            'effective_at' => optional($rate->effective_at)->toIso8601String(),
        ];
    }
}
