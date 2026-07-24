<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('duration_type', ['hourly', 'daily', 'weekly', 'monthly']);
            $table->unsignedInteger('duration_value');
            $table->decimal('total_amount', 24, 2);
            $table->decimal('security_deposit', 24, 2)->default(0);
            $table->boolean('operator_included')->default(false);
            $table->decimal('operator_fee', 24, 2)->nullable();
            $table->enum('status', [
                'pending',
                'confirmed',
                'active',
                'completed',
                'cancelled',
                'overdue',
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_bookings');
    }
};
