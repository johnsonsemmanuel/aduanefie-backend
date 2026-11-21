<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->integer('progress_pct')->default(0);
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->date('scheduled_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_updates');
    }
};
