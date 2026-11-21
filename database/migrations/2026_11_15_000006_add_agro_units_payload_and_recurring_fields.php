<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'payload_capacity_kg')) {
                $table->unsignedInteger('payload_capacity_kg')->nullable()->after('sub_district');
            }
            if (!Schema::hasColumn('delivery_men', 'is_cold_chain')) {
                $table->boolean('is_cold_chain')->default(false)->after('payload_capacity_kg');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'agro_unit')) {
                $table->string('agro_unit', 64)->nullable()->after('unit_id');
            }
            if (!Schema::hasColumn('items', 'unit_weight_kg')) {
                $table->decimal('unit_weight_kg', 8, 2)->nullable()->after('agro_unit');
            }
            if (!Schema::hasColumn('items', 'crop_grade')) {
                $table->string('crop_grade', 64)->nullable()->after('unit_weight_kg');
            }
            if (!Schema::hasColumn('items', 'harvest_date')) {
                $table->date('harvest_date')->nullable()->after('crop_grade');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'recurring_frequency')) {
                $table->string('recurring_frequency', 32)->default('one_time')->after('order_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_men', 'is_cold_chain')) {
                $table->dropColumn('is_cold_chain');
            }
            if (Schema::hasColumn('delivery_men', 'payload_capacity_kg')) {
                $table->dropColumn('payload_capacity_kg');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            foreach (['harvest_date', 'crop_grade', 'unit_weight_kg', 'agro_unit'] as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'recurring_frequency')) {
                $table->dropColumn('recurring_frequency');
            }
        });
    }
};
