<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pest_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('pest_type')->index();
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('description');
            $table->json('photos')->nullable();
            $table->string('status')->default('reported'); // reported, treating, resolved
            $table->text('ai_recommendation')->nullable();
            $table->text('treatment_applied')->nullable();
            $table->timestamp('treated_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('items')->onDelete('cascade');
            $table->index(['vendor_id', 'status']);
            $table->index(['pest_type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pest_reports');
    }
};
