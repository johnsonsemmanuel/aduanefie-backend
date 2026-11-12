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
        Schema::create('buyer_sourcing_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->string('buyer_type')->default('customer');
            $table->string('title');
            $table->text('description');
            $table->string('commodity');
            $table->integer('min_quantity');
            $table->string('unit')->default('kg');
            $table->string('destination_country');
            $table->string('incoterm')->default('FOB');
            $table->float('budget_min')->nullable();
            $table->float('budget_max')->nullable();
            $table->string('currency_code')->default('USD');
            $table->date('needed_by');
            $table->enum('status', ['open', 'matched', 'completed', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['buyer_id', 'buyer_type']);
            $table->index('status');
            $table->index('commodity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('buyer_sourcing_requests');
    }
};
