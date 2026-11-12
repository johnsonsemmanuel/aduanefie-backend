<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stores', function (Blueprint $table) {
            // Which remote marketer owns this store. Nullable because the
            // overwhelming majority of stores have no marketer, and because
            // attribution can be assigned after the store already exists.
            $table->unsignedBigInteger('marketer_id')->nullable()->index();
        });

        Schema::table('admins', function (Blueprint $table) {
            // Per-marketer override of the commission share, expressed as a
            // percentage of the platform commission. Null means "use the
            // configured default", so no backfill is needed.
            $table->decimal('marketer_commission_rate', 6, 3)->nullable();
        });
    }

    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('marketer_id');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('marketer_commission_rate');
        });
    }
};
