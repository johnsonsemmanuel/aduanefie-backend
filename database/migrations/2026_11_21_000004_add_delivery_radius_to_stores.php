<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'delivery_radius_km')) {
                $table->decimal('delivery_radius_km', 5, 2)->nullable()
                    ->after('per_km_shipping_charge')
                    ->comment('Per-store delivery radius in km. If set, nearby farming discovery uses this instead of the global default.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['delivery_radius_km']);
        });
    }
};
