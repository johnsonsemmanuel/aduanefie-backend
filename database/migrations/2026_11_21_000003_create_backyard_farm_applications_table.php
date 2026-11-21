<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backyard_farm_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_email')->nullable()->index();
            $table->string('guest_phone')->nullable()->index();

            // Farm details
            $table->string('farm_name')->nullable();
            $table->string('farm_type')->default('backyard'); // backyard, rooftop, kitchen_garden, etc.
            $table->decimal('farm_size_acres', 8, 3)->nullable();
            $table->text('primary_crops')->nullable(); // comma-separated
            $table->text('farming_experience')->nullable();

            // Location
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('zone_id')->nullable();

            // Photos
            $table->json('farm_photos')->nullable(); // array of photo URLs

            // Consent & terms
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();

            // Workflow
            $table->string('status')->default('pending'); // pending, verified, approved, rejected
            $table->unsignedBigInteger('verified_by')->nullable()->index(); // marketer admin id
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Conversion to vendor
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backyard_farm_applications');
    }
};
