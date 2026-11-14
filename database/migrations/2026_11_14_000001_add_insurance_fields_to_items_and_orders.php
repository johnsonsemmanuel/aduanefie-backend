<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                if (! Schema::hasColumn('items', 'is_insured')) {
                    $table->boolean('is_insured')->default(false);
                }
                if (! Schema::hasColumn('items', 'insurance_type')) {
                    $table->string('insurance_type', 40)->nullable();
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'is_insured')) {
                    $table->boolean('is_insured')->default(false);
                }
                if (! Schema::hasColumn('orders', 'insurance_fee')) {
                    $table->decimal('insurance_fee', 16, 2)->default(0);
                }
                if (! Schema::hasColumn('orders', 'insurance_coverage_type')) {
                    $table->string('insurance_coverage_type', 40)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $cols = array_filter(['is_insured', 'insurance_type'], fn ($c) => Schema::hasColumn('items', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $cols = array_filter(['is_insured', 'insurance_fee', 'insurance_coverage_type'], fn ($c) => Schema::hasColumn('orders', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
