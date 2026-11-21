<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member chat for a shared group buy.
 *
 * Only the organiser and the group's participants may read or write these
 * messages. Membership is checked in the controller against
 * group_buy_participants on every request; a raw group_buy_id in the payload
 * is never treated as proof of membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_buy_messages')) {
            return;
        }

        Schema::create('group_buy_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('group_buy_id')->index();
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->string('sender_guest_id', 64)->nullable();
            // Snapshotted so a later profile rename does not rewrite history.
            $table->string('sender_name', 120);
            $table->text('body');
            $table->timestamps();

            $table->index(['group_buy_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_buy_messages');
    }
};
