<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('d_m_vehicles')) {
            Schema::table('d_m_vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('d_m_vehicles', 'max_weight_kg')) {
                    $table->decimal('max_weight_kg', 10, 2)->nullable()->after('extra_charges');
                }
                if (!Schema::hasColumn('d_m_vehicles', 'per_km_rate')) {
                    $table->decimal('per_km_rate', 10, 2)->nullable()->after('max_weight_kg');
                }
                if (!Schema::hasColumn('d_m_vehicles', 'is_cold_chain')) {
                    $table->boolean('is_cold_chain')->default(false)->after('per_km_rate');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'parcel_weight_kg')) {
                    $table->decimal('parcel_weight_kg', 10, 2)->nullable()->after('dm_vehicle_id');
                }
                if (!Schema::hasColumn('orders', 'is_cold_chain')) {
                    $table->boolean('is_cold_chain')->default(false)->after('parcel_weight_kg');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('d_m_vehicles')) {
            Schema::table('d_m_vehicles', function (Blueprint $table) {
                $cols = array_filter(['max_weight_kg', 'per_km_rate', 'is_cold_chain'], fn ($c) => Schema::hasColumn('d_m_vehicles', $c));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $cols = array_filter(['parcel_weight_kg', 'is_cold_chain'], fn ($c) => Schema::hasColumn('orders', $c));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
