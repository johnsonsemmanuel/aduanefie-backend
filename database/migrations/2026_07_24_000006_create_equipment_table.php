<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 24, 2)->nullable();
            $table->decimal('daily_rate', 24, 2)->nullable();
            $table->decimal('weekly_rate', 24, 2)->nullable();
            $table->decimal('monthly_rate', 24, 2)->nullable();
            $table->decimal('security_deposit', 24, 2)->default(0);
            $table->unsignedInteger('min_rental_duration')->default(1);
            $table->unsignedInteger('max_rental_duration')->nullable();
            $table->boolean('requires_delivery')->default(false);
            $table->boolean('self_pickup')->default(true);
            $table->text('condition_notes')->nullable();
            $table->enum('status', ['available', 'maintenance', 'retired'])->default('available');
            $table->boolean('operator_included')->default(false);
            $table->decimal('operator_fee', 24, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
