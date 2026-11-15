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
                if (! Schema::hasColumn('items', 'moq')) {
                    $table->unsignedInteger('moq')->default(1)->after('maximum_cart_quantity');
                }
                if (! Schema::hasColumn('items', 'bulk_pricing_tiers')) {
                    $table->json('bulk_pricing_tiers')->nullable()->after('moq');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'escrow_status')) {
                    $table->string('escrow_status', 30)->default('none')->after('payment_status');
                }
                if (! Schema::hasColumn('orders', 'phytosanitary_cert_ref')) {
                    $table->string('phytosanitary_cert_ref', 120)->nullable()->after('escrow_status');
                }
                if (! Schema::hasColumn('orders', 'origin_cert_ref')) {
                    $table->string('origin_cert_ref', 120)->nullable()->after('phytosanitary_cert_ref');
                }
                if (! Schema::hasColumn('orders', 'inspection_notes')) {
                    $table->text('inspection_notes')->nullable()->after('origin_cert_ref');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                if (Schema::hasColumn('items', 'bulk_pricing_tiers')) {
                    $table->dropColumn('bulk_pricing_tiers');
                }
                if (Schema::hasColumn('items', 'moq')) {
                    $table->dropColumn('moq');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['inspection_notes', 'origin_cert_ref', 'phytosanitary_cert_ref', 'escrow_status'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
