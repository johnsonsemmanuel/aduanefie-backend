<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the geography + landed-cost columns the cross-border flow needs.
 * Every block is guarded so it is safe if a column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_addresses', 'country_code')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('zone_id')->index();
            });
        }

        // Cross-border destinations are usually outside every local zone, so
        // the address can no longer require a zone.
        if (Schema::hasColumn('customer_addresses', 'zone_id')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->unsignedBigInteger('zone_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('stores', 'country_code')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('zone_id')->index();
            });
        }

        if (! Schema::hasColumn('stores', 'ships_cross_border')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->boolean('ships_cross_border')->default(false)->after('country_code');
            });
        }

        // Item-level customs data. `hs_code` may already have been added by the
        // dormant 2026_01_01_000005 migration, hence the guard.
        if (! Schema::hasColumn('items', 'hs_code')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('hs_code')->nullable();
                $table->string('hs_description')->nullable();
            });
        }

        // Billable weight must come from the catalogue, never from the
        // checkout request, otherwise a client can choose its own freight.
        if (! Schema::hasColumn('items', 'weight_kg')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('weight_kg', 10, 3)->nullable()->after('unit_id');
            });
        }

        if (! Schema::hasColumn('items', 'origin_country')) {
            Schema::table('items', function (Blueprint $table) {
                $table->char('origin_country', 2)->nullable()->index();
            });
        }

        $orderColumns = [
            // The order code has always written this, but the live schema does
            // not have the column, so guarded here to unblock checkout.
            'currency_code' => fn (Blueprint $t) => $t->char('currency_code', 3)->nullable(),
            'origin_country' => fn (Blueprint $t) => $t->char('origin_country', 2)->nullable(),
            'destination_country' => fn (Blueprint $t) => $t->char('destination_country', 2)->nullable()->index(),
            'shipping_method_id' => fn (Blueprint $t) => $t->unsignedBigInteger('shipping_method_id')->nullable(),
            'shipping_rate_id' => fn (Blueprint $t) => $t->unsignedBigInteger('shipping_rate_id')->nullable(),
            'shipping_method_name' => fn (Blueprint $t) => $t->string('shipping_method_name')->nullable(),
            'shipping_mode' => fn (Blueprint $t) => $t->string('shipping_mode')->nullable(),
            'incoterm' => fn (Blueprint $t) => $t->string('incoterm', 3)->nullable(),
            'duty_amount' => fn (Blueprint $t) => $t->decimal('duty_amount', 24, 2)->default(0),
            'vat_amount' => fn (Blueprint $t) => $t->decimal('vat_amount', 24, 2)->default(0),
            'excise_amount' => fn (Blueprint $t) => $t->decimal('excise_amount', 24, 2)->default(0),
            'landed_cost_breakdown' => fn (Blueprint $t) => $t->json('landed_cost_breakdown')->nullable(),
            'exchange_rate' => fn (Blueprint $t) => $t->decimal('exchange_rate', 18, 8)->default(1),
            'base_currency' => fn (Blueprint $t) => $t->char('base_currency', 3)->nullable(),
            'destination_currency' => fn (Blueprint $t) => $t->char('destination_currency', 3)->nullable(),
        ];

        foreach ($orderColumns as $column => $definition) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'currency_code', 'origin_country', 'destination_country', 'shipping_method_id', 'shipping_rate_id',
            'shipping_method_name', 'shipping_mode', 'incoterm', 'duty_amount', 'vat_amount',
            'excise_amount', 'landed_cost_breakdown', 'exchange_rate', 'base_currency',
            'destination_currency',
        ] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['weight_kg', 'origin_country', 'hs_code', 'hs_description'] as $column) {
            if (Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['ships_cross_border', 'country_code'] as $column) {
            if (Schema::hasColumn('stores', $column)) {
                Schema::table('stores', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('customer_addresses', 'country_code')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
