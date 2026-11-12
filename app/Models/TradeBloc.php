<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeBloc extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getMemberListAttribute()
    {
        $countries = json_decode($this->member_countries, true);
        return is_array($countries) ? implode(', ', $countries) : $this->member_countries;
    }

    public function getCurrencyListAttribute()
    {
        $currencies = json_decode($this->primary_currencies, true);
        return is_array($currencies) ? implode(', ', $currencies) : $this->primary_currencies;
    }

    public function getLanguagesListAttribute()
    {
        $languages = json_decode($this->common_languages, true);
        return is_array($languages) ? implode(', ', $languages) : $this->common_languages;
    }

    /**
     * Get corridors within this bloc.
     */
    public function corridors()
    {
        $countries = json_decode($this->member_countries, true);
        if (!is_array($countries)) {
            return \App\Models\TradeCorridor::whereRaw('1 = 0');
        }
        return \App\Models\TradeCorridor::whereIn('origin_country', $countries)
            ->whereIn('destination_country', $countries)
            ->where('status', 'active');
    }

    /**
     * Check if a country is a member of this bloc.
     */
    public function isMember(string $country): bool
    {
        $countries = json_decode($this->member_countries, true);
        return is_array($countries) && in_array($country, $countries);
    }
}
