<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('store_type')->default('default')->after('store_business_model');
            $table->string('farm_name')->nullable()->after('store_type');
            $table->integer('growing_area_sqm')->nullable()->after('farm_name');
            $table->json('primary_crops')->nullable()->after('growing_area_sqm');
            $table->string('farming_method')->nullable()->after('primary_crops');
            $table->json('farm_photos')->nullable()->after('farming_method');
            $table->string('ghana_card_number')->nullable()->after('farm_photos');
            $table->string('ghana_card_image')->nullable()->after('ghana_card_number');

            $table->index('store_type');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_store_type_index');
            $table->dropColumn([
                'store_type',
                'farm_name',
                'growing_area_sqm',
                'primary_crops',
                'farming_method',
                'farm_photos',
                'ghana_card_number',
                'ghana_card_image',
            ]);
        });
    }
};
