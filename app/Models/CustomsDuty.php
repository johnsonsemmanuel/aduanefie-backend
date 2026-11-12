<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomsDuty extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'duty_rate_percent' => 'float',
        'vat_rate_percent' => 'float',
        'excise_rate_percent' => 'float',
        'requires_sps_certificate' => 'boolean',
        'requires_phytosanitary' => 'boolean',
        'requires_origin_certificate' => 'boolean',
    ];

    public function scopeForCountry($query, $country)
    {
        return $query->where('destination_country', $country);
    }

    public function scopeByHsCode($query, $hsCode)
    {
        return $query->where('hs_code', 'like', $hsCode . '%');
    }

    /**
     * Find the matching duty record for a country and HS code.
     * Tries exact HS code first, then chapter (first 2 digits), then fallback.
     */
    public static function findDuty(string $destinationCountry, string $hsCode): ?self
    {
        // Try full HS code match
        $duty = self::forCountry($destinationCountry)->where('hs_code', $hsCode)->first();
        if ($duty) {
            return $duty;
        }

        // Try chapter match (first 2 digits)
        $chapter = substr($hsCode, 0, 2);
        $duty = self::forCountry($destinationCountry)->where('hs_code', $chapter)->first();
        if ($duty) {
            return $duty;
        }

        return null;
    }

    /**
     * Calculate estimated customs charges for a shipment.
     *
     * @param float $valueUsd Declared value in USD
     * @param string $destinationCountry
     * @param string $hsCode
     * @return array{duty_usd: float, vat_usd: float, excise_usd: float, total_usd: float, duty_rate: float, vat_rate: float, documents_required: array}
     */
    public static function calculateDuties(float $valueUsd, string $destinationCountry, string $hsCode): array
    {
        $duty = self::findDuty($destinationCountry, $hsCode);

        $dutyRate = $duty ? $duty->duty_rate_percent : 0;
        $vatRate = $duty ? $duty->vat_rate_percent : 0;
        $exciseRate = $duty ? $duty->excise_rate_percent : 0;

        $dutyUsd = round($valueUsd * ($dutyRate / 100), 2);
        $vatUsd = round(($valueUsd + $dutyUsd) * ($vatRate / 100), 2);
        $exciseUsd = round($valueUsd * ($exciseRate / 100), 2);
        $totalUsd = round($dutyUsd + $vatUsd + $exciseUsd, 2);

        $documentsRequired = [];
        if ($duty) {
            if ($duty->requires_sps_certificate) {
                $documentsRequired[] = 'SPS Certificate';
            }
            if ($duty->requires_phytosanitary) {
                $documentsRequired[] = 'Phytosanitary Certificate';
            }
            if ($duty->requires_origin_certificate) {
                $documentsRequired[] = 'Certificate of Origin';
            }
        }

        return [
            'duty_usd' => $dutyUsd,
            'vat_usd' => $vatUsd,
            'excise_usd' => $exciseUsd,
            'total_usd' => $totalUsd,
            'duty_rate' => $dutyRate,
            'vat_rate' => $vatRate,
            'excise_rate' => $exciseRate,
            'documents_required' => $documentsRequired,
        ];
    }
}
