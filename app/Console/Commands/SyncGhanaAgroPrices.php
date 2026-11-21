<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class SyncGhanaAgroPrices extends Command
{
    protected $signature = 'agro-prices:sync';
    protected $description = 'Sync live Ghana agricultural commodity prices from World Bank RTFP and FAO FPMA (MoFA SRID)';

    public function handle(): int
    {
        if (! Schema::hasTable('agro_market_prices')) {
            $this->warn('agro_market_prices table does not exist.');
            return self::SUCCESS;
        }

        $synced = 0;
        $synced += $this->syncFaoFpma();
        $synced += $this->syncWorldBankRtfp();

        $this->info("Synced {$synced} Ghana commodity price rows.");
        return self::SUCCESS;
    }

    protected function syncFaoFpma(): int
    {
        $count = 0;
        try {
            $seriesRes = Http::timeout(25)
                ->acceptJson()
                ->get('https://fpma.fao.org/giews/v4/price_module/api/v1/FpmaSerieDomestic/', [
                    'iso3_country_code' => 'GHA',
                    'format' => 'json',
                ]);

            if (! $seriesRes->successful()) {
                return 0;
            }

            $results = $seriesRes->json('results', []);
            if (! is_array($results) || empty($results)) {
                return 0;
            }

            // Pick the freshest Ghana series per commodity (preferring 2025/2026 observations)
            $selected = [];
            foreach ($results as $row) {
                $commodity = trim((string) ($row['commodity_name'] ?? ''));
                $market = trim((string) ($row['market_name'] ?? ''));
                $uuid = trim((string) ($row['uuid'] ?? ''));
                if (! $commodity || ! $market || ! $uuid) {
                    continue;
                }

                $endDate = '';
                foreach ($row['periodicity'] ?? [] as $p) {
                    if (($p['period'] ?? '') === 'monthly' && ! empty($p['end_date'])) {
                        $endDate = $p['end_date'];
                    }
                }
                if ($endDate < '2024-01-01') {
                    continue;
                }

                $key = strtolower($commodity . '|' . $market);
                if (! isset($selected[$key]) || $endDate > $selected[$key]['end_date']) {
                    $selected[$key] = [
                        'uuid' => $uuid,
                        'commodity' => $commodity,
                        'market' => $market,
                        'unit' => trim((string) ($row['measure_unit_label'] ?? '100 kg')),
                        'end_date' => $endDate,
                    ];
                }
            }

            if (empty($selected)) {
                return 0;
            }

            // Batch fetch prices in chunks of 15 UUIDs
            $chunks = array_chunk(array_values($selected), 15);
            foreach ($chunks as $chunk) {
                $uuids = implode(',', array_column($chunk, 'uuid'));
                $priceRes = Http::timeout(25)
                    ->acceptJson()
                    ->get('https://fpma.fao.org/giews/v4/price_module/api/v1/FpmaSeriePrice/', [
                        'uuid__in' => $uuids,
                        'periodicity' => 'monthly',
                        'format' => 'json',
                    ]);

                if (! $priceRes->successful()) {
                    continue;
                }

                $payload = $priceRes->json();
                $seriesList = isset($payload['results']) ? $payload['results'] : (isset($payload['datapoints']) ? [$payload] : (is_array($payload) ? $payload : []));

                $metaByUuid = [];
                foreach ($chunk as $m) {
                    $metaByUuid[$m['uuid']] = $m;
                }

                foreach ($seriesList as $series) {
                    $uuid = $series['uuid'] ?? null;
                    $pts = $series['datapoints'] ?? [];
                    if (! $uuid || ! isset($metaByUuid[$uuid]) || empty($pts)) {
                        continue;
                    }

                    $meta = $metaByUuid[$uuid];
                    $latest = $pts[0] ?? null;
                    $prev = $pts[1] ?? null;
                    $wholesale = (float) ($latest['price_value'] ?? 0);
                    if ($wholesale <= 0) {
                        continue;
                    }

                    $prevPrice = (float) ($prev['price_value'] ?? 0);
                    $pct = $prevPrice > 0 ? round((($wholesale - $prevPrice) / $prevPrice) * 100, 2) : 0.0;
                    $retail = round($wholesale * 1.15, 2);
                    $recordedAt = ! empty($latest['date']) ? Carbon::parse($latest['date']) : now();

                    $this->upsertPrice([
                        'commodity' => $this->normalizeCommodityLabel($meta['commodity']),
                        'category' => $this->inferCategory($meta['commodity']),
                        'unit' => $this->normalizeUnitLabel($meta['unit']),
                        'market_hub' => $meta['market'] . ' (MoFA)',
                        'wholesale_price_ghs' => round($wholesale, 2),
                        'retail_price_ghs' => $retail,
                        'price_change_pct' => $pct,
                        'trend' => $pct > 0.3 ? 'up' : ($pct < -0.3 ? 'down' : 'stable'),
                        'source_platform' => 'FAO FPMA / MoFA SRID',
                        'recorded_at' => $recordedAt,
                    ]);
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FAO FPMA sync failed', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    protected function syncWorldBankRtfp(): int
    {
        $count = 0;
        $tmpZip = sys_get_temp_dir() . '/aduanefie_wb_rtfp_' . getmypid() . '.zip';

        try {
            $res = Http::timeout(20)
                ->acceptJson()
                ->get('https://microdata.worldbank.org/index.php/api/catalog/GHA_2021_RTFP_v02_M/resources');

            if (! $res->successful()) {
                return 0;
            }

            $resources = $res->json('resources', []);
            if (! is_array($resources)) {
                $resources = is_array($res->json()) ? $res->json() : [];
            }

            $zipUrl = null;
            foreach ($resources as $r) {
                $fname = (string) ($r['filename'] ?? '');
                if (str_contains($fname, 'GHA_RTFP_mkt_') && str_ends_with($fname, '.zip') && ! empty($r['url'])) {
                    $zipUrl = $r['url'];
                    break;
                }
            }

            if (! $zipUrl) {
                return 0;
            }

            $dl = Http::timeout(60)->sink($tmpZip)->get($zipUrl);
            if (! $dl->successful() || ! file_exists($tmpZip)) {
                return 0;
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                return 0;
            }

            $csvName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.csv')) {
                    $csvName = $name;
                    break;
                }
            }
            if (! $csvName) {
                $zip->close();
                return 0;
            }

            $stream = $zip->getStream($csvName);
            if (! $stream) {
                $zip->close();
                return 0;
            }

            $headers = fgetcsv($stream);
            if (! is_array($headers)) {
                fclose($stream);
                $zip->close();
                return 0;
            }
            $idx = array_flip($headers);

            $targetMarkets = ['Accra', 'Kumasi', 'Tamale', 'Techiman', 'Ejura'];
            $commodityCols = [
                'c_maize' => ['label' => 'Maize (Dry)', 'unit' => '100 kg bag', 'cat' => 'Grains & Cereals', 'inf' => 'inflation_maize'],
                'c_yam' => ['label' => 'Yam (White / Pona)', 'unit' => '100 tubers', 'cat' => 'Tubers & Roots', 'inf' => 'inflation_yam'],
                'c_tomatoes_fao' => ['label' => 'Tomatoes (Fresh)', 'unit' => '52 kg crate', 'cat' => 'Vegetables', 'inf' => 'inflation_tomatoes_fao'],
                'c_cassava_fao' => ['label' => 'Cassava (Fresh Tubers)', 'unit' => '91 kg bag', 'cat' => 'Tubers & Roots', 'inf' => 'inflation_cassava_fao'],
                'c_cowpeas_fao' => ['label' => 'Cowpeas / Beans', 'unit' => '109 kg bag', 'cat' => 'Legumes & Oilseeds', 'inf' => 'inflation_cowpeas_fao'],
                'c_groundnuts_fao' => ['label' => 'Groundnuts (Shelled)', 'unit' => '100 kg bag', 'cat' => 'Legumes & Oilseeds', 'inf' => 'inflation_groundnuts_fao'],
                'c_sorghum' => ['label' => 'Sorghum', 'unit' => '109 kg bag', 'cat' => 'Grains & Cereals', 'inf' => 'inflation_sorghum'],
                'c_millet' => ['label' => 'Millet', 'unit' => '93 kg bag', 'cat' => 'Grains & Cereals', 'inf' => 'inflation_millet'],
            ];

            $latestByKey = [];
            while (($row = fgetcsv($stream)) !== false) {
                $mkt = $row[$idx['mkt_name'] ?? -1] ?? '';
                $date = $row[$idx['price_date'] ?? -1] ?? '';
                if (! in_array($mkt, $targetMarkets, true) || $date < '2025-01-01') {
                    continue;
                }

                foreach ($commodityCols as $col => $meta) {
                    $cIdx = $idx[$col] ?? -1;
                    if ($cIdx < 0 || empty($row[$cIdx]) || ! is_numeric($row[$cIdx])) {
                        continue;
                    }
                    $price = (float) $row[$cIdx];
                    if ($price <= 0) {
                        continue;
                    }
                    $key = $col . '|' . $mkt;
                    if (! isset($latestByKey[$key]) || $date > $latestByKey[$key]['date']) {
                        $infIdx = $idx[$meta['inf']] ?? -1;
                        $pct = ($infIdx >= 0 && isset($row[$infIdx]) && is_numeric($row[$infIdx]))
                            ? round((float) $row[$infIdx], 2)
                            : 0.0;
                        $latestByKey[$key] = [
                            'date' => $date,
                            'market' => $mkt,
                            'price' => $price,
                            'pct' => $pct,
                            'meta' => $meta,
                        ];
                    }
                }
            }

            fclose($stream);
            $zip->close();

            // Keep top 2 representative hubs per commodity so the table stays scannable
            $perCommodity = [];
            foreach ($latestByKey as $item) {
                $lbl = $item['meta']['label'];
                $perCommodity[$lbl][] = $item;
            }

            foreach ($perCommodity as $lbl => $items) {
                usort($items, fn ($a, $b) => strcmp($b['date'], $a['date']));
                foreach (array_slice($items, 0, 2) as $entry) {
                    $wholesale = round($entry['price'], 2);
                    $retail = round($wholesale * 1.16, 2);
                    $pct = $entry['pct'];
                    $this->upsertPrice([
                        'commodity' => $lbl,
                        'category' => $entry['meta']['cat'],
                        'unit' => $entry['meta']['unit'],
                        'market_hub' => $entry['market'] . ' (WB RTFP)',
                        'wholesale_price_ghs' => $wholesale,
                        'retail_price_ghs' => $retail,
                        'price_change_pct' => $pct,
                        'trend' => $pct > 0.3 ? 'up' : ($pct < -0.3 ? 'down' : 'stable'),
                        'source_platform' => 'World Bank RTFP',
                        'recorded_at' => Carbon::parse($entry['date']),
                    ]);
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('World Bank RTFP sync failed', ['error' => $e->getMessage()]);
        } finally {
            @unlink($tmpZip);
        }

        return $count;
    }

    protected function upsertPrice(array $data): void
    {
        $existing = DB::table('agro_market_prices')
            ->where('commodity', $data['commodity'])
            ->where('market_hub', $data['market_hub'])
            ->first();

        if ($existing) {
            DB::table('agro_market_prices')
                ->where('id', $existing->id)
                ->update([
                    'category' => $data['category'],
                    'unit' => $data['unit'],
                    'wholesale_price_ghs' => $data['wholesale_price_ghs'],
                    'retail_price_ghs' => $data['retail_price_ghs'],
                    'price_change_pct' => $data['price_change_pct'],
                    'trend' => $data['trend'],
                    'source_platform' => $data['source_platform'],
                    'is_active' => true,
                    'recorded_at' => $data['recorded_at'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('agro_market_prices')->insert(array_merge($data, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function normalizeCommodityLabel(string $name): string
    {
        return match ($name) {
            'Maize' => 'White Maize',
            'Rice (aromatic)' => 'Local Rice (Aromatic)',
            'Yam (white)' => 'Yam (White / Pona)',
            'Cowpeas (white)' => 'Cowpeas (White)',
            'Groundnuts (red)' => 'Groundnuts (Red)',
            default => $name,
        };
    }

    protected function normalizeUnitLabel(string $unit): string
    {
        return str_contains(strtolower($unit), 'bag') || str_contains(strtolower($unit), 'liter')
            ? $unit
            : $unit . ' bag';
    }

    protected function inferCategory(string $name): string
    {
        $l = strtolower($name);
        if (str_contains($l, 'maize') || str_contains($l, 'rice') || str_contains($l, 'millet') || str_contains($l, 'sorghum')) {
            return 'Grains & Cereals';
        }
        if (str_contains($l, 'yam') || str_contains($l, 'cassava') || str_contains($l, 'gari')) {
            return 'Tubers & Roots';
        }
        if (str_contains($l, 'cowpea') || str_contains($l, 'groundnut') || str_contains($l, 'soy') || str_contains($l, 'sesame')) {
            return 'Legumes & Oilseeds';
        }
        if (str_contains($l, 'oil')) {
            return 'Oils & Fats';
        }
        if (str_contains($l, 'banana') || str_contains($l, 'orange') || str_contains($l, 'plantain')) {
            return 'Fruits & Plantain';
        }
        return 'Vegetables';
    }
}
