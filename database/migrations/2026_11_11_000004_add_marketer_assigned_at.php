<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a marketer was attached to a store.
 *
 * Without this, attribution was retroactive: accrual credits every delivered
 * order from an attributed store that has no commission row yet, so attaching
 * a marketer to an established store would pay them a share of every order the
 * store had ever delivered, in one scan. That is an unbounded liability created
 * by a single dropdown.
 *
 * Commission now only accrues for orders delivered on or after this timestamp.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('marketer_assigned_at')->nullable()->after('marketer_id');
        });

        // No store currently has a marketer, but if one did, treat the
        // attribution as starting now rather than paying its history.
        DB::table('stores')
            ->whereNotNull('marketer_id')
            ->update(['marketer_assigned_at' => now()]);
    }

    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('marketer_assigned_at');
        });
    }
};
