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
        Schema::create('trade_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->string('buyer_type')->comment('customer or vendor');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('quantity');
            $table->string('unit')->default('kg');
            $table->string('destination_country');
            $table->string('incoterm')->default('FOB');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'quoted', 'accepted', 'rejected', 'completed'])->default('open');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('items')->onDelete('cascade');
            $table->index(['buyer_id', 'buyer_type']);
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
        Schema::dropIfExists('trade_requests');
    }
};
