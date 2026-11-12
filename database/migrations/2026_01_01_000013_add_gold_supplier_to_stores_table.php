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
            $table->boolean('gold_supplier')->default(0)->after('verification_status')->comment('Paid visibility tier');
            $table->date('gold_supplier_expires_at')->nullable()->after('gold_supplier');
            $table->index('gold_supplier');
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
            $table->dropColumn(['gold_supplier', 'gold_supplier_expires_at']);
        });
    }
};
