<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currency_rates') && ! Schema::hasColumn('currency_rates', 'source')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                $table->string('source')->nullable()->after('rate');
            });
        }

        if (Schema::hasTable('shipping_rates')) {
            if (! Schema::hasColumn('shipping_rates', 'source')) {
                Schema::table('shipping_rates', function (Blueprint $table) {
                    $table->string('source')->nullable()->after('currency_code');
                });
            }

            if (! Schema::hasColumn('shipping_rates', 'effective_at')) {
                Schema::table('shipping_rates', function (Blueprint $table) {
                    $table->timestamp('effective_at')->nullable()->after('source');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipping_rates')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                if (Schema::hasColumn('shipping_rates', 'effective_at')) {
                    $table->dropColumn('effective_at');
                }
                if (Schema::hasColumn('shipping_rates', 'source')) {
                    $table->dropColumn('source');
                }
            });
        }

        if (Schema::hasTable('currency_rates')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                if (Schema::hasColumn('currency_rates', 'source')) {
                    $table->dropColumn('source');
                }
            });
        }
    }
};
