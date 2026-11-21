<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared group buying ("shared buy").
 *
 * A group buy is one aggregate purchase of a single item from a single store,
 * split equally across a fixed number of members. Each member gets their own
 * order (so the supplier ships to each address and each member pays their own
 * delivery). No money is captured until the group is full: an expired or
 * cancelled group therefore has nothing to refund.
 *
 * `target_value_ghs` is a target, not a hard-coded minimum. 2000 is only the
 * suggested example; the organiser (or an admin) can set it higher or lower.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_buys')) {
            Schema::create('group_buys', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->unsignedBigInteger('organizer_id')->nullable();
                $table->string('organizer_guest_id', 64)->nullable();

                $table->string('status', 20)->default('open')->index();
                $table->decimal('target_value_ghs', 12, 2)->default(2000.00);
                $table->decimal('farmer_price_ghs', 12, 2);
                $table->unsignedInteger('target_units');
                $table->unsignedInteger('share_units');
                $table->unsignedTinyInteger('member_limit')->default(5);
                $table->unsignedTinyInteger('committed_count')->default(0);

                $table->string('destination_country', 2)->nullable()->index();
                $table->boolean('is_cross_border')->default(false);
                $table->string('incoterm', 10)->nullable();
                $table->string('currency_code', 10)->default('GHS');

                $table->timestamp('closes_at')->nullable()->index();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('group_buy_participants')) {
            Schema::create('group_buy_participants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_buy_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('guest_id', 64)->nullable();

                $table->unsignedInteger('share_units');
                $table->decimal('item_share_ghs', 12, 2);
                $table->decimal('delivery_charge_ghs', 12, 2)->default(0.00);
                $table->json('delivery_address')->nullable();

                // No charge is taken at join time. A locked group's participants
                // pay through their own order; a failed group simply releases
                // them, so `refunded` is only used if a captured payment ever
                // needs reversing (e.g. a later wallet-based version).
                $table->string('payment_status', 20)->default('not_charged');
                $table->string('fulfillment_status', 20)->default('pending');

                $table->timestamp('joined_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique(['group_buy_id', 'user_id']);
                $table->unique(['group_buy_id', 'guest_id']);
            });
        }

        if (! Schema::hasTable('group_buy_events')) {
            Schema::create('group_buy_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_buy_id')->index();
                $table->unsignedBigInteger('participant_id')->nullable();
                $table->string('event', 40);
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'group_buy_id')) {
                    $table->unsignedBigInteger('group_buy_id')->nullable()->index()->after('module_id');
                }
                if (! Schema::hasColumn('orders', 'group_buy_participant_id')) {
                    // One member share can produce at most one order. This is
                    // the final guard against a double checkout race.
                    $table->unsignedBigInteger('group_buy_participant_id')->nullable()->unique()->after('group_buy_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['group_buy_participant_id', 'group_buy_id'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('group_buy_events');
        Schema::dropIfExists('group_buy_participants');
        Schema::dropIfExists('group_buys');
    }
};
