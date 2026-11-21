<?php

namespace App\Models;

use App\Services\Markets\MarketProfileService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomsDuty extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_country',
        'hs_code',
        'hs_description',
        'duty_rate_percent',
        'vat_rate_percent',
        'excise_rate_percent',
        'requires_sps_certificate',
        'requires_phytosanitary',
        'requires_origin_certificate',
    ];

    protected $casts = [
        'duty_rate_percent' => 'float',
        'vat_rate_percent' => 'float',
        'excise_rate_percent' => 'float',
        'requires_sps_certificate' => 'boolean',
        'requires_phytosanitary' => 'boolean',
        'requires_origin_certificate' => 'boolean',
    ];

    /**
     * Resolve the rule for a destination + HS code. Falls back to the `*`
     * (destination-wide) rule when no item-specific rule exists.
     */
    public static function resolve(string $destinationCountry, ?string $hsCode): ?self
    {
        $raw = trim($destinationCountry);
        $markets = app(MarketProfileService::class);
        $code = $markets->normalizeCountry($raw);
        $profile = $code ? $markets->find($code) : null;

        // Seed data historically used full country names while quotes pass ISO
        // codes. Match both shapes so duty never silently resolves to zero.
        $candidates = array_values(array_unique(array_filter([
            $raw,
            strtoupper($raw),
            $code,
            $profile['name'] ?? null,
        ])));

        $rules = static::whereIn('destination_country', $candidates)->get();

        if ($hsCode) {
            $exact = $rules
                ->filter(fn (self $rule) => $rule->hs_code === $hsCode || str_starts_with($hsCode, (string) $rule->hs_code))
                ->sortByDesc(fn (self $rule) => strlen((string) $rule->hs_code))
                ->first();

            if ($exact) {
                return $exact;
            }
        }

        return $rules->firstWhere('hs_code', '*');
    }
}
