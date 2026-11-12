<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $rates = [
            'USD' => 1.00,
            'GHS' => 15.00,
            'NGN' => 1500.00,
            'KES' => 130.00,
            'XOF' => 600.00,
            'XAF' => 600.00,
            'ETB' => 55.00,
            'UGX' => 3700.00,
            'TZS' => 2500.00,
            'ZAR' => 18.00,
            'GMD' => 50.00,
            'GNF' => 8500.00,
            'LRD' => 185.00,
            'MWK' => 1700.00,
            'MZN' => 64.00,
            'SLL' => 22000.00,
            'SOS' => 15000.00,
            'SDG' => 1300.00,
            'TND' => 3.10,
            'MAD' => 10.00,
            'DZD' => 135.00,
            'LYD' => 4.80,
        ];

        foreach ($rates as $code => $rate) {
            Currency::where('currency_code', $code)->update(['exchange_rate' => $rate]);
        }
    }
}
