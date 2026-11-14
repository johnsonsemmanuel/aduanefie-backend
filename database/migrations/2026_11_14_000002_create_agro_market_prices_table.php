<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agro_market_prices')) {
            Schema::create('agro_market_prices', function (Blueprint $table) {
                $table->id();
                $table->string('commodity', 120);
                $table->string('category', 80)->default('Grains & Cereals');
                $table->string('unit', 60)->default('100 kg bag');
                $table->string('market_hub', 100)->default('Techiman Market');
                $table->decimal('wholesale_price_ghs', 12, 2)->default(0);
                $table->decimal('retail_price_ghs', 12, 2)->default(0);
                $table->decimal('price_change_pct', 6, 2)->default(0);
                $table->string('trend', 16)->default('stable');
                $table->string('source_platform', 80)->default('GCX / MoFA SRID');
                $table->boolean('is_active')->default(true);
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agro_market_prices');
    }
};
