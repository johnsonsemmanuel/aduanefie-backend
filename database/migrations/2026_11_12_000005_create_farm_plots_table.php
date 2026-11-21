<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('name');
            $table->string('plot_code')->unique()->index();
            $table->decimal('size_acres', 8, 2)->nullable();
            $table->string('crop_type')->nullable();
            $table->string('variety')->nullable();
            $table->date('planting_date')->nullable();
            $table->date('expected_harvest_date')->nullable();
            $table->string('status')->default('planning'); // planning, planting, growing, harvest_ready, fallow
            $table->string('soil_type')->nullable();
            $table->string('irrigation_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index(['vendor_id', 'status']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_plots');
    }
};
