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
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            if (Schema::hasIndex($table, $indexName)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fall through to driver-specific check
        }

        if (DB::getDriverName() === 'pgsql') {
            $found = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );
            if ($found) {
                return true;
            }
            $constraint = DB::selectOne(
                'SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?',
                [$table, $indexName]
            );
            return (bool) $constraint;
        }

        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('marketer_commissions')) {
            return;
        }

        if ($this->indexExists('marketer_commissions', 'marketer_commissions_order_id_unique')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->dropUnique('marketer_commissions_order_id_unique');
            });
        }

        Schema::table('marketer_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();

            if (! Schema::hasColumn('marketer_commissions', 'type')) {
                $table->string('type', 20)->default('order')->after('id');
            }
            if (! Schema::hasColumn('marketer_commissions', 'bounty_key')) {
                $table->string('bounty_key', 191)->nullable()->after('order_transaction_id');
            }
        });

        if (! $this->indexExists('marketer_commissions', 'marketer_commissions_bounty_key_unique')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->unique('bounty_key', 'marketer_commissions_bounty_key_unique');
            });
        }

        if (! $this->indexExists('marketer_commissions', 'marketer_commissions_marketer_type_index')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->index(['marketer_id', 'type'], 'marketer_commissions_marketer_type_index');
            });
        }

        $this->backfillExistingBounties();
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketer_commissions')) {
            return;
        }

        DB::table('marketer_commissions')->whereNull('order_id')->delete();

        if ($this->indexExists('marketer_commissions', 'marketer_commissions_marketer_type_index')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->dropIndex('marketer_commissions_marketer_type_index');
            });
        }
        if ($this->indexExists('marketer_commissions', 'marketer_commissions_bounty_key_unique')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->dropUnique('marketer_commissions_bounty_key_unique');
            });
        }

        Schema::table('marketer_commissions', function (Blueprint $table) {
            foreach (['type', 'bounty_key'] as $col) {
                if (Schema::hasColumn('marketer_commissions', $col)) {
                    $table->dropColumn($col);
                }
            }
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });

        if (! $this->indexExists('marketer_commissions', 'marketer_commissions_order_id_unique')) {
            Schema::table('marketer_commissions', function (Blueprint $table) {
                $table->unique('order_id', 'marketer_commissions_order_id_unique');
            });
        }
    }

    private function backfillExistingBounties(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasTable('stores')) {
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

            $key = $store->marketer_id . ':' . $store->store_id;
            $exists = DB::table('marketer_commissions')->where('bounty_key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('marketer_commissions')->insert([
                'type' => 'bounty',
                'marketer_id' => $store->marketer_id,
                'store_id' => $store->store_id,
                'order_id' => null,
                'order_transaction_id' => null,
                'bounty_key' => $key,
                'order_amount' => 0,
                'commission_amount' => $amount,
                'status' => 'eligible',
                'eligible_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
