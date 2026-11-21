<?php

namespace Database\Seeders;

use App\Models\CurrencyRate;
use Illuminate\Database\Seeder;

/**
 * Operator-reviewed baseline rates. They are deliberately labelled
 * `manual_baseline` with no effective_at so the UI can show that they are not
 * live. Schedule `market:rates:refresh` to replace them with dated rates.
 */
class CurrencyRateSeeder extends Seeder
{
    public function run()
    {
        $perUsd = [
            'GHS' => 15.0,
            'NGN' => 1500.0,
            'XOF' => 600.0,
            'KES' => 130.0,
            'ZAR' => 18.0,
        ];

        $rows = [];
        foreach ($perUsd as $code => $rate) {
            $rows[] = ['base_currency' => 'USD', 'quote_currency' => $code, 'rate' => $rate];
            $rows[] = ['base_currency' => $code, 'quote_currency' => 'USD', 'rate' => round(1 / $rate, 8)];

            if ($code === 'GHS') {
                continue;
            }

            $fromGhs = round($rate / $perUsd['GHS'], 8);
            $rows[] = ['base_currency' => 'GHS', 'quote_currency' => $code, 'rate' => $fromGhs];
            $rows[] = ['base_currency' => $code, 'quote_currency' => 'GHS', 'rate' => round(1 / $fromGhs, 8)];
        }

        foreach ($rows as $row) {
            CurrencyRate::updateOrInsert(
                ['base_currency' => $row['base_currency'], 'quote_currency' => $row['quote_currency']],
                $row + ['source' => 'manual_baseline', 'effective_at' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
