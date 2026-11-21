<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consent dialogue: each group can carry its own consent text; each
        // participant records when they accepted it and which version.
        Schema::table('group_buys', function (Blueprint $table) {
            if (! Schema::hasColumn('group_buys', 'consent_text')) {
                $table->text('consent_text')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('group_buys', 'consent_version')) {
                $table->string('consent_version', 20)->nullable()->after('consent_text');
            }
            // Pooled escrow state: when the group is fully paid (all members
            // have checked out), this is set and funds can be released to the
            // store. Until then, money sits in per-member orders.
            if (! Schema::hasColumn('group_buys', 'escrow_status')) {
                $table->string('escrow_status', 20)->default('pending')->after('currency_code');
            }
            if (! Schema::hasColumn('group_buys', 'escrow_released_at')) {
                $table->timestamp('escrow_released_at')->nullable()->after('fulfilled_at');
            }
        });

        Schema::table('group_buy_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('group_buy_participants', 'consent_accepted_at')) {
                $table->timestamp('consent_accepted_at')->nullable()->after('joined_at');
            }
            if (! Schema::hasColumn('group_buy_participants', 'consent_version')) {
                $table->string('consent_version', 20)->nullable()->after('consent_accepted_at');
            }
            if (! Schema::hasColumn('group_buy_participants', 'notification_last_sent_at')) {
                $table->timestamp('notification_last_sent_at')->nullable()->after('released_at');
            }
            if (! Schema::hasColumn('group_buy_participants', 'gps_lat')) {
                $table->decimal('gps_lat', 10, 7)->nullable()->after('delivery_address');
            }
            if (! Schema::hasColumn('group_buy_participants', 'gps_lng')) {
                $table->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_buys', function (Blueprint $table) {
            $table->dropColumn([
                'consent_text', 'consent_version', 'escrow_status', 'escrow_released_at',
            ]);
        });
        Schema::table('group_buy_participants', function (Blueprint $table) {
            $table->dropColumn([
                'consent_accepted_at', 'consent_version', 'notification_last_sent_at', 'gps_lat', 'gps_lng',
            ]);
        });
    }
};
