<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'community_delivery_timeout'],
            ['value' => '240', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('business_settings')->where('key', 'community_delivery_timeout')->delete();
    }
};
