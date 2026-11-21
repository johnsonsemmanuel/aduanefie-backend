<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per refer-a-friend group invite. Kept so repeated sends to the same
 * address can be rate limited and so an admin can see how a group spread.
 */
class CreateGroupBuyInvitesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('group_buy_invites')) {
            return;
        }

        Schema::create('group_buy_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_buy_id')->index();
            $table->unsignedBigInteger('sender_user_id')->index();
            $table->string('email');
            $table->string('message', 300)->nullable();
            $table->timestamps();

            $table->index(['group_buy_id', 'email']);
            $table->index(['sender_user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_buy_invites');
    }
}
