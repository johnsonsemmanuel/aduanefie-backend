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
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('verification_tier', ['smallholder', 'coop', 'aggregator', 'exporter'])->nullable()->after('featured');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('verification_tier');
            $table->text('verification_documents')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_documents');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['verification_tier', 'verification_status', 'verification_documents', 'verified_at']);
        });
    }
};
