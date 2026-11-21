<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regional_tours')) {
            Schema::create('regional_tours', function (Blueprint $table) {
                $table->id();
                $table->string('title', 180);
                $table->string('region', 80)->index();
                $table->string('district', 100)->nullable();
                $table->string('venue', 255)->nullable();
                $table->date('start_date')->index();
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('lead_marketer_id')->nullable()->index();
                $table->unsignedInteger('capacity')->default(100);
                $table->string('status', 30)->default('open')->index(); // upcoming, open, in_progress, completed, cancelled
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('regional_tour_registrations')) {
            Schema::create('regional_tour_registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tour_id')->nullable()->index();
                $table->string('farmer_name', 150);
                $table->string('phone', 40)->index();
                $table->string('email', 150)->nullable();
                $table->string('region', 80)->index();
                $table->string('community', 150)->nullable();
                $table->decimal('farm_size_acres', 10, 2)->nullable();
                $table->string('primary_crops', 500)->nullable();
                $table->boolean('is_cooperative')->default(false);
                $table->unsignedInteger('farmer_count')->default(1);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedBigInteger('marketer_id')->nullable()->index();
                $table->string('status', 30)->default('registered')->index(); // registered, confirmed, visited, onboarded, no_show
                $table->timestamp('checked_in_at')->nullable();
                $table->unsignedBigInteger('converted_store_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('backyard_farm_applications') && ! Schema::hasColumn('backyard_farm_applications', 'marketer_id')) {
            Schema::table('backyard_farm_applications', function (Blueprint $table) {
                $table->unsignedBigInteger('marketer_id')->nullable()->after('zone_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('backyard_farm_applications') && Schema::hasColumn('backyard_farm_applications', 'marketer_id')) {
            Schema::table('backyard_farm_applications', function (Blueprint $table) {
                $table->dropColumn('marketer_id');
            });
        }
        Schema::dropIfExists('regional_tour_registrations');
        Schema::dropIfExists('regional_tours');
    }
};
