<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketer commission ledger.
 *
 * One commission row per delivered order, enforced by a unique index on
 * order_id. That makes accrual idempotent: the accrual command can be run as
 * often as it likes and cannot pay twice for the same order.
 *
 * Money is stored as the source values it was computed from (base_amount and
 * rate) as well as the result (amount), so an admin can audit a payout without
 * re-deriving anything from current store settings, which may since have
 * changed.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('marketer_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketer_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('order_transaction_id')->nullable()->index();

            $table->decimal('base_amount', 24, 2);
            $table->decimal('rate', 6, 3);
            $table->decimal('amount', 24, 2);
            $table->string('currency', 10);

            // pending   = earned, holding period not finished (or on hold)
            // eligible  = past the holding period, can be requested
            // in_payout = attached to a payout awaiting admin approval
            // paid      = payout was marked paid
            // void      = order was canceled/refunded/failed
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('eligible_at')->nullable()->index();
            $table->unsignedBigInteger('payout_id')->nullable()->index();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();
        });

        Schema::create('marketer_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketer_id')->index();
            $table->decimal('amount', 24, 2);
            $table->string('currency', 10);

            $table->string('method', 30)->default('mobile_money');
            $table->string('network', 50)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('country', 10)->nullable();

            // requested = marketer asked, money not yet approved
            // approved  = admin approved, not yet sent
            // paid      = admin recorded the mobile-money transfer
            // rejected  = admin refused; commissions return to eligible
            $table->string('status', 20)->default('requested')->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketer_payouts');
        Schema::dropIfExists('marketer_commissions');
    }
};
