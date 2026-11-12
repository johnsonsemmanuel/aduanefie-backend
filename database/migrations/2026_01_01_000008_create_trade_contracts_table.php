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
        Schema::create('trade_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_request_id')->unique();
            $table->unsignedBigInteger('trade_quote_id')->nullable();
            $table->unsignedBigInteger('buyer_id');
            $table->string('buyer_type');
            $table->unsignedBigInteger('seller_id');
            $table->string('seller_type')->default('vendor');
            $table->float('contract_amount');
            $table->string('currency_code')->default('GHS');
            $table->float('escrow_amount')->default(0);
            $table->enum('payment_status', ['pending', 'deposited', 'released', 'refunded', 'disputed'])->default('pending');
            $table->enum('delivery_status', ['pending', 'in_transit', 'delivered', 'docs_verified', 'completed'])->default('pending');
            $table->date('delivery_confirmed_at')->nullable();
            $table->date('docs_verified_at')->nullable();
            $table->date('funds_released_at')->nullable();
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->foreign('trade_request_id')->references('id')->on('trade_requests')->onDelete('cascade');
            $table->foreign('trade_quote_id')->references('id')->on('trade_request_quotes')->onDelete('cascade');
            $table->index('payment_status');
            $table->index('delivery_status');
            $table->index('buyer_id');
            $table->index('seller_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trade_contracts');
    }
};
