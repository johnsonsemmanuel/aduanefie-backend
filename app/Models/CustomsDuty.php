<?php

namespace App\Models;

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
        $destinationCountry = strtoupper($destinationCountry);

        $rules = static::where('destination_country', $destinationCountry)->get();

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
