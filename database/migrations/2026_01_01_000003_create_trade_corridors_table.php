<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_corridors', function (Blueprint $table) {
            $table->id();
            $table->string('origin_country');
            $table->string('destination_country');
            $table->string('corridor_name')->comment('e.g. Ghana-Nigeria, Kenya-Uganda');
            $table->float('distance_km')->nullable();
            $table->integer('transit_days_estimate')->nullable()->comment('Estimated transit time in days');
            $table->float('trucking_rate_per_ton_usd')->default(0)->comment('Rate per ton in USD');
            $table->float('minimum_charge_usd')->default(0)->comment('Minimum charge in USD');
            $table->boolean('cold_chain_available')->default(0);
            $table->boolean('warehousing_available')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['origin_country', 'destination_country']);
            $table->index('corridor_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trade_corridors');
    }
};
