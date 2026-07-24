<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_extra_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('equipment_bookings')->cascadeOnDelete();
            $table->string('charge_type');
            $table->decimal('amount', 24, 2);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_extra_charges');
    }
};
