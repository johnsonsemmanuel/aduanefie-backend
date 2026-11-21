<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (!Schema::hasColumn('stores', 'region')) {
                    $table->string('region', 120)->nullable();
                }
                if (!Schema::hasColumn('stores', 'district')) {
                    $table->string('district', 120)->nullable()->after('region');
                }
                if (!Schema::hasColumn('stores', 'sub_district')) {
                    $table->string('sub_district', 120)->nullable()->after('district');
                }
            });
        }

        if (Schema::hasTable('delivery_men')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                if (!Schema::hasColumn('delivery_men', 'region')) {
                    $table->string('region', 120)->nullable()->after('delivery_scope');
                }
                if (!Schema::hasColumn('delivery_men', 'district')) {
                    $table->string('district', 120)->nullable()->after('region');
                }
                if (!Schema::hasColumn('delivery_men', 'sub_district')) {
                    $table->string('sub_district', 120)->nullable()->after('district');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                $cols = array_filter(['district', 'sub_district'], fn ($c) => Schema::hasColumn('stores', $c));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
        if (Schema::hasTable('delivery_men')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $cols = array_filter(['region', 'district', 'sub_district'], fn ($c) => Schema::hasColumn('delivery_men', $c));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
