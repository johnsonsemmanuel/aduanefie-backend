<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregation_hubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('name');
            $table->string('hub_code')->unique()->index();
            $table->string('address');
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('country_code')->default('GH');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable()->comment('Max capacity in kg');
            $table->decimal('current_utilization_kg', 10, 2)->default(0);
            $table->string('status')->default('active'); // active, inactive, at_capacity
            $table->text('services')->nullable()->comment('JSON: cold_chain, sorting, grading, packaging');
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
        Schema::dropIfExists('aggregation_hubs');
    }
};
