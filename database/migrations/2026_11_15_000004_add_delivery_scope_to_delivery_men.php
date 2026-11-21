<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_men') && !Schema::hasColumn('delivery_men', 'delivery_scope')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->string('delivery_scope', 32)->default('local_farm')->after('vehicle_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_men') && Schema::hasColumn('delivery_men', 'delivery_scope')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->dropColumn('delivery_scope');
            });
        }
    }
};
