<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshMarketRates extends Command
{
    protected $signature = 'market:rates:refresh';

    protected $description = 'Refresh cross-border FX rates from the configured live source';

    public function handle(): int
    {
        $base = strtoupper((string) config('market_profiles.fx_base_currency', 'USD'));
        $currencies = (array) config('market_profiles.fx_currencies', []);

        try {
            $response = Http::timeout(15)->retry(2, 500)->get("https://open.er-api.com/v6/latest/{$base}");
        } catch (Throwable $exception) {
            Log::warning('Market FX refresh failed', ['error' => $exception->getMessage()]);
            $this->error('Could not reach the FX provider. Existing rates were left unchanged.');

            return self::FAILURE;
        }

        if (! $response->successful() || ! $response->json('rates')) {
            Log::warning('Market FX refresh returned an invalid response', ['status' => $response->status()]);
            $this->error('FX provider returned an invalid response. Existing rates were left unchanged.');

            return self::FAILURE;
        }

        $rates = $response->json('rates');
        $effectiveAt = now();
        $updated = 0;

        foreach ($currencies as $quote) {
            $quote = strtoupper((string) $quote);
            $rate = $quote === $base ? 1.0 : (float) ($rates[$quote] ?? 0);

            if ($rate <= 0) {
                continue;
            }

            CurrencyRate::updateOrCreate(
                ['base_currency' => $base, 'quote_currency' => $quote],
                [
                    'rate' => $rate,
                    'source' => 'open.er-api.com',
                    'effective_at' => $effectiveAt,
                ]
            );

            if ($quote !== $base) {
                CurrencyRate::updateOrCreate(
                    ['base_currency' => $quote, 'quote_currency' => $base],
                    [
                        'rate' => round(1 / $rate, 8),
                        'source' => 'open.er-api.com',
                        'effective_at' => $effectiveAt,
                    ]
                );
            }

            $updated++;
        }

        $this->info("Updated {$updated} market FX rates from open.er-api.com.");

        return self::SUCCESS;
    }
}
