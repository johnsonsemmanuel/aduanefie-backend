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
        Schema::create('bulk_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->integer('min_quantity');
            $table->integer('max_quantity')->nullable()->comment('NULL means unlimited');
            $table->float('discount_percent')->default(0)->comment('Discount percentage off base price');
            $table->float('price_override')->nullable()->comment('Absolute price per unit, overrides discount');
            $table->string('currency_code')->default('GHS');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->index(['item_id', 'min_quantity']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bulk_pricing_tiers');
    }
};
