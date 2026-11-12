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
        Schema::create('trade_assurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_contract_id')->unique();
            $table->string('assurance_type')->comment('quality, quantity, both');
            $table->enum('status', ['pending', 'inspected', 'passed', 'failed', 'waived'])->default('pending');
            $table->string('inspector_name')->nullable();
            $table->string('inspector_organization')->nullable();
            $table->date('inspection_date')->nullable();
            $table->text('inspection_report')->nullable();
            $table->text('findings')->nullable();
            $table->float('inspection_fee')->default(0);
            $table->string('currency_code')->default('USD');
            $table->string('certificate_number')->nullable();
            $table->date('certificate_expiry')->nullable();
            $table->string('certificate_file')->nullable();
            $table->timestamps();

            $table->foreign('trade_contract_id')->references('id')->on('trade_contracts')->onDelete('cascade');
            $table->index('status');
            $table->index('trade_contract_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trade_assurances');
    }
};
