<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('standing_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->string('buyer_type')->default('customer');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('store_id');
            $table->integer('quantity');
            $table->string('unit')->default('kg');
            $table->string('frequency')->comment('weekly, monthly, quarterly, seasonal');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('destination_country');
            $table->string('incoterm')->default('FOB');
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->date('last_order_date')->nullable();
            $table->date('next_order_date');
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index(['buyer_id', 'buyer_type']);
            $table->index('status');
            $table->index('next_order_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('standing_orders');
    }
};
