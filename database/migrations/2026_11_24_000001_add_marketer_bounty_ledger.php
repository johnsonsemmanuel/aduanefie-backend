<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the onboarding bounty a real, payable ledger entry.
 *
 * Until now the dashboard derived a bounty from a store count, but the payout
 * flow only reserved commission rows. The number was displayed and could never
 * be withdrawn. A bounty is now its own row in the same ledger, so the same
 * reserve → approve → record-transfer rules apply to it.
 *
 * bounty_key is "{marketer_id}:{store_id}" and unique, which makes crediting
 * idempotent: the same verified farmer can never pay the same bounty twice.
 * order_id becomes nullable because a bounty has no order behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_commissions', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->string('type', 20)->default('order')->after('id');
            $table->string('bounty_key', 191)->nullable()->after('order_transaction_id');

            $table->unique('bounty_key', 'marketer_commissions_bounty_key_unique');
            $table->index(['marketer_id', 'type'], 'marketer_commissions_marketer_type_index');
        });

        $this->backfillExistingBounties();
    }

    public function down(): void
    {
        // Bounty rows cannot exist under the old order-unique schema.
        DB::table('marketer_commissions')->whereNull('order_id')->delete();

        Schema::table('marketer_commissions', function (Blueprint $table) {
            $table->dropIndex('marketer_commissions_marketer_type_index');
            $table->dropUnique('marketer_commissions_bounty_key_unique');
            $table->dropColumn(['type', 'bounty_key']);
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->unique('order_id', 'marketer_commissions_order_id_unique');
        });
    }

    /**
     * Credit farmers that were already attributed and verified when this
     * migration runs, so the ledger matches what the dashboard has been
     * showing. Idempotent via bounty_key.
     */
    private function backfillExistingBounties(): void
    {
        if (!Schema::hasTable('admins') || !Schema::hasTable('stores')) {
            return;
        }

        $defaultBounty = (float) config('marketer.default_onboarding_bounty', 25);
        $hasVerifiedFlag = Schema::hasColumn('stores', 'marketer_verified');
        $hasBountyRate = Schema::hasColumn('admins', 'onboarding_bounty_ghs');

        $stores = DB::table('stores')
            ->join('admins', 'admins.id', '=', 'stores.marketer_id')
            ->where('admins.role_id', (int) config('marketer.role_id', 5))
            ->where(function ($query) use ($hasVerifiedFlag) {
                $query->where('stores.status', 1);
                if ($hasVerifiedFlag) {
                    $query->orWhere('stores.marketer_verified', true);
                }
            })
            ->select(
                'stores.id as store_id',
                'stores.marketer_id',
                $hasBountyRate ? 'admins.onboarding_bounty_ghs as bounty_rate' : DB::raw('NULL as bounty_rate')
            )
            ->orderBy('stores.id')
            ->get();

        $now = now();
        foreach ($stores as $store) {
            $amount = round(($store->bounty_rate !== null && $store->bounty_rate !== '')
                ? (float) $store->bounty_rate
                : $defaultBounty, 2);

            if ($amount <= 0) {
                continue;
            }

            DB::table('marketer_commissions')->insertOrIgnore([
                'marketer_id' => $store->marketer_id,
                'store_id' => $store->store_id,
                'order_id' => null,
                'order_transaction_id' => null,
                'type' => 'bounty',
                'bounty_key' => $store->marketer_id . ':' . $store->store_id,
                'base_amount' => $amount,
                'rate' => 100,
                'amount' => $amount,
                'currency' => (string) (config('marketer.currency') ?: 'GHS'),
                'status' => 'eligible',
                'eligible_at' => $now,
                'payout_id' => null,
                'void_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
