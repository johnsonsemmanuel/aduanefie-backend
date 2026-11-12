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
        Schema::create('trade_request_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_request_id');
            $table->unsignedBigInteger('seller_id');
            $table->string('seller_type')->comment('vendor');
            $table->float('quoted_price');
            $table->string('currency_code')->default('GHS');
            $table->date('estimated_delivery')->nullable();
            $table->text('terms')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('trade_request_id')->references('id')->on('trade_requests')->onDelete('cascade');
            $table->index('trade_request_id');
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
        Schema::dropIfExists('trade_request_quotes');
    }
};
