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
        // `certification` does not exist on every environment (it is absent on
        // the live schema), so don't position after it. Guard duplicates too:
        // the cross-border migration may have added these columns already.
        if (! Schema::hasColumn('items', 'hs_code')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('hs_code')->nullable()->comment('Harmonized System code for customs');
                $table->string('hs_description')->nullable()->comment('HS code description');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['hs_code', 'hs_description']);
        });
    }
};
