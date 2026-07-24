<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('community_agent_id')->nullable()->after('delivery_man_id');
            $table->boolean('is_community_delivery')->default(0)->after('community_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['community_agent_id', 'is_community_delivery']);
        });
    }
};
