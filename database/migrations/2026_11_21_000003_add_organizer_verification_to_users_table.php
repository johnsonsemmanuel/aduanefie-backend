<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified-organiser trust tier for group buys.
 *
 * Only a masked card reference is stored. The full Ghana Card number and any
 * document image stay with the user; admin reviews the submission out of band.
 * This tier never blocks group creation, it only powers a trust badge.
 */
class AddOrganizerVerificationToUsersTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'organizer_verification_status')) {
                $table->string('organizer_verification_status', 20)->default('unverified');
            }
            if (! Schema::hasColumn('users', 'ghana_card_last4')) {
                $table->string('ghana_card_last4', 4)->nullable();
            }
            if (! Schema::hasColumn('users', 'organizer_verification_submitted_at')) {
                $table->timestamp('organizer_verification_submitted_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'organizer_verified_at')) {
                $table->timestamp('organizer_verified_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'organizer_verification_note')) {
                $table->text('organizer_verification_note')->nullable();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'organizer_verification_status',
                'ghana_card_last4',
                'organizer_verification_submitted_at',
                'organizer_verified_at',
                'organizer_verification_note',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
