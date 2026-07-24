<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_condition_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('equipment_bookings')->cascadeOnDelete();
            $table->enum('report_type', ['pre_rental', 'post_rental']);
            $table->enum('reported_by', ['customer', 'provider']);
            $table->tinyInteger('condition_rating');
            $table->text('notes')->nullable();
            $table->json('images')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_condition_reports');
    }
};
