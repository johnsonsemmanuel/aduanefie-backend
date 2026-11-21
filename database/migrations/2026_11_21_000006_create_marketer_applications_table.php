<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketer_applications')) {
            Schema::create('marketer_applications', function (Blueprint $table) {
                $table->id();
                $table->string('full_name', 150);
                $table->string('email', 150)->index();
                $table->string('phone', 40)->index();
                $table->string('region', 80)->index();
                $table->string('district', 120)->nullable();
                $table->string('momo_network', 50)->nullable();
                $table->text('experience_notes')->nullable();
                $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('created_admin_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_applications');
    }
};
