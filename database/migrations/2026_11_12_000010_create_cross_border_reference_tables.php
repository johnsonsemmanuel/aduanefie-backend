<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data for cross-border ordering:
 *  - countries              : the destination/source list shown in checkout.
 *  - shipping_methods       : the selectable logistics options (road/air/sea/...).
 *  - shipping_rates         : per-lane price/ETA for a shipping method.
 *  - currency_rates         : conversion used when a rate is not in the store currency.
 *
 * Guarded with hasTable so it is safe on environments that already ran the
 * dormant trade migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->char('code', 2)->unique();
                $table->string('name');
                $table->char('currency_code', 3)->nullable();
                $table->string('phone_code', 8)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_methods')) {
            Schema::create('shipping_methods', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('carrier')->nullable();
                $table->enum('mode', ['road', 'air', 'sea', 'rail', 'courier'])->default('road');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_rates')) {
            Schema::create('shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
                $table->char('origin_country', 2);
                $table->char('destination_country', 2);
                $table->decimal('min_weight', 10, 3)->default(0);
                $table->decimal('max_weight', 10, 3)->nullable();
                $table->decimal('base_rate', 12, 3)->default(0);
                $table->decimal('per_kg_rate', 12, 3)->default(0);
                $table->unsignedSmallInteger('min_days')->nullable();
                $table->unsignedSmallInteger('max_days')->nullable();
                $table->char('currency_code', 3);
                $table->boolean('cod_allowed')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(
                    ['origin_country', 'destination_country', 'is_active'],
                    'shipping_rates_lane_idx'
                );
                $table->index('shipping_method_id');
            });
        }

        if (! Schema::hasTable('currency_rates')) {
            Schema::create('currency_rates', function (Blueprint $table) {
                $table->id();
                $table->char('base_currency', 3);
                $table->char('quote_currency', 3);
                $table->decimal('rate', 18, 8);
                $table->timestamp('effective_at')->nullable();
                $table->timestamps();

                $table->unique(['base_currency', 'quote_currency']);
            });
        }

        // Live never ran the dormant trade migration that owned this table, so
        // create it here rather than relying on that migration.
        if (! Schema::hasTable('customs_duties')) {
            Schema::create('customs_duties', function (Blueprint $table) {
                $table->id();
                $table->string('destination_country');
                $table->string('hs_code')->comment('HS code, chapter prefix, or * for a destination default');
                $table->string('hs_description');
                $table->float('duty_rate_percent')->default(0);
                $table->float('vat_rate_percent')->default(0);
                $table->float('excise_rate_percent')->default(0);
                $table->boolean('requires_sps_certificate')->default(0);
                $table->boolean('requires_phytosanitary')->default(0);
                $table->boolean('requires_origin_certificate')->default(0);
                $table->timestamps();

                $table->index(['destination_country', 'hs_code']);
                $table->index('destination_country');
            });
        }

        $this->seedCountries();
        $this->seedShippingMethods();
        $this->seedBaseCurrencyRate();
    }

    /**
     * Static reference data — safe to (re)apply. Business-specific shipping
     * rates are NOT seeded here; they must be configured per lane.
     */
    private function seedCountries(): void
    {
        $now = now();
        $countries = [
            ['code' => 'GH', 'name' => 'Ghana', 'currency_code' => 'GHS', 'phone_code' => '233'],
            ['code' => 'NG', 'name' => 'Nigeria', 'currency_code' => 'NGN', 'phone_code' => '234'],
            ['code' => 'KE', 'name' => 'Kenya', 'currency_code' => 'KES', 'phone_code' => '254'],
            ['code' => 'CI', 'name' => "Côte d'Ivoire", 'currency_code' => 'XOF', 'phone_code' => '225'],
            ['code' => 'SN', 'name' => 'Senegal', 'currency_code' => 'XOF', 'phone_code' => '221'],
            ['code' => 'CM', 'name' => 'Cameroon', 'currency_code' => 'XAF', 'phone_code' => '237'],
            ['code' => 'TG', 'name' => 'Togo', 'currency_code' => 'XOF', 'phone_code' => '228'],
            ['code' => 'BJ', 'name' => 'Benin', 'currency_code' => 'XOF', 'phone_code' => '229'],
            ['code' => 'BF', 'name' => 'Burkina Faso', 'currency_code' => 'XOF', 'phone_code' => '226'],
            ['code' => 'ML', 'name' => 'Mali', 'currency_code' => 'XOF', 'phone_code' => '223'],
            ['code' => 'SL', 'name' => 'Sierra Leone', 'currency_code' => 'SLE', 'phone_code' => '232'],
            ['code' => 'LR', 'name' => 'Liberia', 'currency_code' => 'LRD', 'phone_code' => '231'],
            ['code' => 'GM', 'name' => 'Gambia', 'currency_code' => 'GMD', 'phone_code' => '220'],
            ['code' => 'GN', 'name' => 'Guinea', 'currency_code' => 'GNF', 'phone_code' => '224'],
            ['code' => 'ZA', 'name' => 'South Africa', 'currency_code' => 'ZAR', 'phone_code' => '27'],
            ['code' => 'UG', 'name' => 'Uganda', 'currency_code' => 'UGX', 'phone_code' => '256'],
            ['code' => 'TZ', 'name' => 'Tanzania', 'currency_code' => 'TZS', 'phone_code' => '255'],
            ['code' => 'RW', 'name' => 'Rwanda', 'currency_code' => 'RWF', 'phone_code' => '250'],
            ['code' => 'ET', 'name' => 'Ethiopia', 'currency_code' => 'ETB', 'phone_code' => '251'],
            ['code' => 'ZM', 'name' => 'Zambia', 'currency_code' => 'ZMW', 'phone_code' => '260'],
            ['code' => 'ZW', 'name' => 'Zimbabwe', 'currency_code' => 'USD', 'phone_code' => '263'],
            ['code' => 'BW', 'name' => 'Botswana', 'currency_code' => 'BWP', 'phone_code' => '267'],
            ['code' => 'EG', 'name' => 'Egypt', 'currency_code' => 'EGP', 'phone_code' => '20'],
            ['code' => 'MA', 'name' => 'Morocco', 'currency_code' => 'MAD', 'phone_code' => '212'],
            ['code' => 'US', 'name' => 'United States', 'currency_code' => 'USD', 'phone_code' => '1'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'currency_code' => 'GBP', 'phone_code' => '44'],
            ['code' => 'AE', 'name' => 'United Arab Emirates', 'currency_code' => 'AED', 'phone_code' => '971'],
            ['code' => 'CN', 'name' => 'China', 'currency_code' => 'CNY', 'phone_code' => '86'],
            ['code' => 'IN', 'name' => 'India', 'currency_code' => 'INR', 'phone_code' => '91'],
        ];

        foreach ($countries as $i => $country) {
            DB::table('countries')->updateOrInsert(
                ['code' => $country['code']],
                $country + ['is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function seedShippingMethods(): void
    {
        $now = now();
        $methods = [
            ['code' => 'road_freight', 'name' => 'Road Freight', 'mode' => 'road', 'sort_order' => 1, 'description' => 'Cross-border trucking on the selected corridor.'],
            ['code' => 'air_freight', 'name' => 'Air Freight', 'mode' => 'air', 'sort_order' => 2, 'description' => 'Air cargo to the destination airport.'],
            ['code' => 'sea_freight', 'name' => 'Sea Freight', 'mode' => 'sea', 'sort_order' => 3, 'description' => 'Container / break-bulk sea freight.'],
            ['code' => 'courier', 'name' => 'International Courier', 'mode' => 'courier', 'sort_order' => 4, 'description' => 'Door-to-door courier for small parcels.'],
        ];

        foreach ($methods as $method) {
            DB::table('shipping_methods')->updateOrInsert(
                ['code' => $method['code']],
                $method + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function seedBaseCurrencyRate(): void
    {
        $platformCurrency = 'GHS';
        DB::table('currency_rates')->updateOrInsert(
            ['base_currency' => $platformCurrency, 'quote_currency' => $platformCurrency],
            ['rate' => 1, 'effective_at' => now(), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_duties');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('countries');
    }
};
