<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie a regional-tour application to the customer account that submitted it.
 *
 * The column stays nullable because registrations created before this change
 * were anonymous and must not be silently deleted. New applications are
 * rejected at the controller if there is no authenticated user, so the gate
 * cannot be bypassed by omitting the field.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regional_tour_registrations')
            || Schema::hasColumn('regional_tour_registrations', 'user_id')) {
            return;
        }

        Schema::table('regional_tour_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('regional_tour_registrations')
            || ! Schema::hasColumn('regional_tour_registrations', 'user_id')) {
            return;
        }

        Schema::table('regional_tour_registrations', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
