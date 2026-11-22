<?php

use App\Services\Markets\MarketProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zones existed before the market-profile work and had no country, so a
 * numeric zone id could be replayed against a different market after a
 * country switch. Add a nullable market code and backfill the legacy rows to
 * the configured business country (the platform's original single market).
 * Rows that cannot be mapped stay NULL and therefore fail closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zones')) {
            return;
        }

        if (! Schema::hasColumn('zones', 'country_code')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('name')->index();
            });
        }

        $configured = null;
        if (Schema::hasTable('business_settings')) {
            $raw = DB::table('business_settings')->where('key', 'country')->value('value');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $configured = is_string($decoded) ? $decoded : $raw;
            }
        }

        $country = app(MarketProfileService::class)->normalizeCountry(
            $configured !== null && $configured !== '' ? (string) $configured : null
        ) ?? app(MarketProfileService::class)->defaultCountry();

        DB::table('zones')->whereNull('country_code')->update(['country_code' => $country]);
    }

    public function down(): void
    {
        if (Schema::hasTable('zones') && Schema::hasColumn('zones', 'country_code')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
