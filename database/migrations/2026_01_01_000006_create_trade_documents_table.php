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
        Schema::create('trade_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_request_id')->nullable();
            $table->unsignedBigInteger('trade_quote_id')->nullable();
            $table->string('document_type')->comment('sps_certificate, phytosanitary, origin_certificate, export_permit, other');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('storage')->default('public');
            $table->string('issued_by')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('trade_request_id')->references('id')->on('trade_requests')->onDelete('cascade');
            $table->foreign('trade_quote_id')->references('id')->on('trade_request_quotes')->onDelete('cascade');
            $table->index('trade_request_id');
            $table->index('trade_quote_id');
            $table->index('document_type');
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
        Schema::dropIfExists('trade_documents');
    }
};
