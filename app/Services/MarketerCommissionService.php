<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Store;
use App\Scopes\ZoneScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marketer commission rules.
 *
 * The decisions this encodes:
 *  - A marketer owns a store (stores.marketer_id); every delivered order from
 *    that store credits that marketer.
 *  - The commission is a share of the platform commission actually recorded on
 *    order_transactions, never a share of gross order value, so a payout can
 *    never exceed what the platform earned.
 *  - Only stores on the 'commission' business model accrue. Subscription and
 *    unsubscribed stores earned the platform a fee instead of per-order
 *    commission, so there is nothing to share.
 *  - A commission becomes requestable 14 days after delivery (configurable).
 *
 * Every write is idempotent or explicitly state-guarded. The accrual can be run
 * on a schedule and by hand without double-crediting: one row per order_id, and
 * inserts ignore duplicates.
 *
 * All queries here remove the ZoneScope global scope. That scope filters by the
 * *logged-in* admin's zone, which would make an accrual or a payout total depend
 * on who happened to click the button. Marketer money must not.
 */
class MarketerCommissionService
{
    /** Order statuses that permanently disqualify a commission. */
    private const VOID_ORDER_STATUSES = ['canceled', 'refunded', 'failed'];

    /** Transaction statuses that mean the platform commission was reversed. */
    private const VOID_TRANSACTION_STATUSES = [
        'refunded_with_delivery_charge',
        'refunded_without_delivery_charge',
    ];

    /** Order statuses that put an accrued commission on hold, not void. */
    private const HOLD_ORDER_STATUSES = ['refund_requested', 'refund_request_canceled'];

    public function currency(): string
    {
        $configured = config('marketer.currency');
        if (!empty($configured)) {
            return (string) $configured;
        }

        $value = BusinessSetting::where('key', 'currency')->value('value');

        return $value ? (string) $value : 'GHS';
    }

    /**
     * Create missing commissions for delivered orders.
     *
     * Returns the number of rows created. Safe to call repeatedly.
     */
    public function accrue(): int
    {
        $created = 0;
        $currency = $this->currency();
        $holdingDays = (int) config('marketer.holding_days', 14);

        Order::withoutGlobalScope(ZoneScope::class)
            ->where('order_status', 'delivered')
            ->whereNotNull('store_id')
            ->whereDoesntHave('marketerCommission')
            ->whereHas('store', function ($query) {
                $query->withoutGlobalScope(ZoneScope::class)
                    ->whereNotNull('marketer_id')
                    ->whereNotNull('marketer_assigned_at')
                    ->where('store_business_model', 'commission');
            })
            ->whereHas('transaction', function ($query) {
                $query->withoutGlobalScope(ZoneScope::class)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhereNotIn('status', self::VOID_TRANSACTION_STATUSES);
                    });
            })
            ->with([
                'store' => function ($query) {
                    $query->withoutGlobalScope(ZoneScope::class)->with('marketer');
                },
                'transaction' => function ($query) {
                    $query->withoutGlobalScope(ZoneScope::class);
                },
            ])
            ->chunkById(200, function ($orders) use (&$created, $currency, $holdingDays) {
                foreach ($orders as $order) {
                    $created += $this->accrueOrder($order, $currency, $holdingDays);
                }
            });

        return $created;
    }

    /**
     * Accrue a single order if it qualifies. Returns 1 when a row was created.
     */
    public function accrueOrder(Order $order, ?string $currency = null, ?int $holdingDays = null): int
    {
        $store = $order->store;
        if (!$store || !$store->marketer_id) {
            return 0;
        }

        if ($store->store_business_model !== 'commission') {
            return 0;
        }

        if (!$store->marketer_assigned_at) {
            // Attributed but no start date: we cannot tell which orders the
            // marketer is owed for, and paying history would be a liability
            // they were never promised.
            return 0;
        }

        // Forward-only. Orders delivered before the marketer was attached
        // belong to whoever ran the store then, not to the new marketer.
        $deliveredForComparison = $order->delivered ?: $order->updated_at;
        if ($deliveredForComparison
            && Carbon::parse($deliveredForComparison)->lt(Carbon::parse($store->marketer_assigned_at))) {
            return 0;
        }

        $marketer = $store->marketer;
        if (!$marketer) {
            // Attribution points at an admin that no longer exists. Do not pay
            // anyone else; leave it unattributed and visible in the report.
            return 0;
        }

        $transaction = $order->transaction;
        if (!$transaction || in_array($transaction->status, self::VOID_TRANSACTION_STATUSES, true)) {
            return 0;
        }

        $base = round((float) $transaction->admin_commission, 2);
        if ($base <= 0) {
            // No platform commission means there is nothing to share. A zero
            // row would only add noise to the ledger.
            return 0;
        }

        $rate = $marketer->marketerCommissionRate();
        $amount = round($base * $rate / 100, 2, PHP_ROUND_HALF_UP);
        if ($amount <= 0) {
            return 0;
        }

        $deliveredAt = $order->delivered ?: $order->updated_at ?: now();
        $eligibleAt = Carbon::parse($deliveredAt)
            ->addDays($holdingDays ?? (int) config('marketer.holding_days', 14));

        return DB::table('marketer_commissions')->insertOrIgnore([
            'marketer_id' => $marketer->id,
            'store_id' => $store->id,
            'order_id' => $order->id,
            'order_transaction_id' => $transaction->id,
            'base_amount' => $base,
            'rate' => $rate,
            'amount' => $amount,
            'currency' => $currency ?? $this->currency(),
            'status' => MarketerCommission::STATUS_PENDING,
            'eligible_at' => $eligibleAt,
            'payout_id' => null,
            'void_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reconcile existing commissions with the orders they came from, then move
     * matured commissions to eligible.
     *
     * @return array{voided:int,held:int,restored:int,released:int}
     */
    public function refresh(): array
    {
        return DB::transaction(function () {
            $voided = $this->voidDisqualified();
            $held = $this->holdUnderRefund();
            $restored = $this->restoreDelivered();
            $released = $this->releaseMatured();

            return [
                'voided' => $voided,
                'held' => $held,
                'restored' => $restored,
                'released' => $released,
            ];
        });
    }

    /**
     * Void commissions whose order was canceled/refunded/failed.
     *
     * Commissions already paid are left alone: the transfer happened. A refund
     * after payout is a collections matter, not a ledger rewrite.
     */
    private function voidDisqualified(): int
    {
        $candidates = MarketerCommission::query()
            ->whereIn('status', [
                MarketerCommission::STATUS_PENDING,
                MarketerCommission::STATUS_ELIGIBLE,
                MarketerCommission::STATUS_IN_PAYOUT,
            ])
            ->where(function ($query) {
                $query->whereIn('order_id', $this->orderIdsWithStatus(self::VOID_ORDER_STATUSES))
                    ->orWhereIn('order_id', $this->refundedTransactionOrderIds());
            })
            ->with('payout')
            ->get();

        $count = 0;
        foreach ($candidates as $commission) {
            if ($commission->payout && $commission->payout->status === MarketerPayout::STATUS_PAID) {
                continue;
            }

            $commission->status = MarketerCommission::STATUS_VOID;
            $commission->void_reason = 'order_canceled_or_refunded';
            $commission->save();
            $count++;

            if ($commission->payout_id) {
                $this->reconcilePayout($commission->payout);
            }
        }

        return $count;
    }

    /**
     * Park commissions whose order has a refund in flight. They are not voided,
     * because the refund may be refused, but they cannot be paid while it is
     * open.
     */
    private function holdUnderRefund(): int
    {
        $orderIds = $this->orderIdsWithStatus(self::HOLD_ORDER_STATUSES);
        if ($orderIds->isEmpty()) {
            return 0;
        }

        return MarketerCommission::query()
            ->whereIn('status', [MarketerCommission::STATUS_PENDING, MarketerCommission::STATUS_ELIGIBLE])
            ->whereIn('order_id', $orderIds)
            ->update([
                'status' => MarketerCommission::STATUS_PENDING,
                'eligible_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Recompute the holding date for commissions that were held and whose order
     * is delivered again (the refund was refused).
     */
    private function restoreDelivered(): int
    {
        $orderIds = $this->orderIdsWithStatus(['delivered']);
        if ($orderIds->isEmpty()) {
            return 0;
        }

        $holdingDays = (int) config('marketer.holding_days', 14);
        $restored = 0;

        MarketerCommission::query()
            ->where('status', MarketerCommission::STATUS_PENDING)
            ->whereNull('eligible_at')
            ->whereIn('order_id', $orderIds)
            ->with(['order' => function ($query) {
                $query->withoutGlobalScope(ZoneScope::class);
            }])
            ->get()
            ->each(function (MarketerCommission $commission) use ($holdingDays, &$restored) {
                $order = $commission->order;
                if (!$order) {
                    return;
                }
                $commission->eligible_at = Carbon::parse($order->delivered ?: $order->updated_at)
                    ->addDays($holdingDays);
                $commission->save();
                $restored++;
            });

        return $restored;
    }

    /** Move matured pending commissions to eligible. */
    private function releaseMatured(): int
    {
        return MarketerCommission::query()
            ->where('status', MarketerCommission::STATUS_PENDING)
            ->whereNotNull('eligible_at')
            ->where('eligible_at', '<=', now())
            ->update([
                'status' => MarketerCommission::STATUS_ELIGIBLE,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reserve a marketer's eligible commissions into a payout request.
     *
     * The reservation is what stops the same commission being requested twice.
     */
    public function requestPayout(Admin $marketer, array $details): MarketerPayout
    {
        return DB::transaction(function () use ($marketer, $details) {
            $commissions = MarketerCommission::query()
                ->where('marketer_id', $marketer->id)
                ->where('status', MarketerCommission::STATUS_ELIGIBLE)
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                throw new RuntimeException('You have no eligible commission to request.');
            }

            $currencies = $commissions->pluck('currency')->unique();
            if ($currencies->count() > 1) {
                throw new RuntimeException('Commissions in more than one currency cannot be paid in one payout.');
            }

            $amount = round((float) $commissions->sum('amount'), 2);
            $minimum = (float) config('marketer.min_payout', 0);
            if ($minimum > 0 && $amount < $minimum) {
                throw new RuntimeException(
                    'The minimum payout is ' . number_format($minimum, 2) . ' ' . $currencies->first() . '.'
                );
            }

            $payout = MarketerPayout::create([
                'marketer_id' => $marketer->id,
                'amount' => $amount,
                'currency' => $currencies->first(),
                'method' => 'mobile_money',
                'network' => $details['network'] ?? null,
                'account_name' => $details['account_name'] ?? null,
                'account_number' => $details['account_number'] ?? null,
                'country' => $details['country'] ?? null,
                'status' => MarketerPayout::STATUS_REQUESTED,
                'requested_at' => now(),
                'note' => $details['note'] ?? null,
            ]);

            MarketerCommission::whereIn('id', $commissions->pluck('id'))
                ->update([
                    'payout_id' => $payout->id,
                    'status' => MarketerCommission::STATUS_IN_PAYOUT,
                    'updated_at' => now(),
                ]);

            return $payout->fresh(['commissions']);
        });
    }

    /** Admin approves a request. No money has moved yet. */
    public function approve(MarketerPayout $payout, Admin $admin): MarketerPayout
    {
        return DB::transaction(function () use ($payout, $admin) {
            $payout = MarketerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== MarketerPayout::STATUS_REQUESTED) {
                throw new RuntimeException('Only a requested payout can be approved.');
            }

            $this->assertCommissionsStillPayable($payout);

            $payout->status = MarketerPayout::STATUS_APPROVED;
            $payout->approved_by = $admin->id;
            $payout->approved_at = now();
            $payout->save();

            return $payout->fresh(['commissions']);
        });
    }

    /** Admin refuses a request; reserved commissions become requestable again. */
    public function reject(MarketerPayout $payout, Admin $admin, string $reason): MarketerPayout
    {
        return DB::transaction(function () use ($payout, $admin, $reason) {
            $payout = MarketerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (!in_array($payout->status, [MarketerPayout::STATUS_REQUESTED, MarketerPayout::STATUS_APPROVED], true)) {
                throw new RuntimeException('Only a requested or approved payout can be rejected.');
            }

            $payout->status = MarketerPayout::STATUS_REJECTED;
            $payout->approved_by = $admin->id;
            $payout->rejection_reason = $reason;
            $payout->save();

            MarketerCommission::where('payout_id', $payout->id)
                ->where('status', MarketerCommission::STATUS_IN_PAYOUT)
                ->update([
                    'payout_id' => null,
                    'status' => MarketerCommission::STATUS_ELIGIBLE,
                    'updated_at' => now(),
                ]);

            return $payout->fresh(['commissions']);
        });
    }

    /**
     * Admin records the mobile-money transfer. This is the only transition that
     * marks money as sent, and it requires a reference so the payment can be
     * audited against the mobile-money statement.
     */
    public function markPaid(MarketerPayout $payout, Admin $admin, string $reference): MarketerPayout
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new RuntimeException('A payment reference is required.');
        }

        return DB::transaction(function () use ($payout, $admin, $reference) {
            $payout = MarketerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== MarketerPayout::STATUS_APPROVED) {
                throw new RuntimeException('Only an approved payout can be marked paid.');
            }

            $this->assertCommissionsStillPayable($payout);

            $payout->status = MarketerPayout::STATUS_PAID;
            $payout->paid_at = now();
            $payout->reference = $reference;
            $payout->approved_by = $payout->approved_by ?: $admin->id;
            $payout->save();

            MarketerCommission::where('payout_id', $payout->id)
                ->where('status', MarketerCommission::STATUS_IN_PAYOUT)
                ->update([
                    'status' => MarketerCommission::STATUS_PAID,
                    'updated_at' => now(),
                ]);

            return $payout->fresh(['commissions']);
        });
    }

    /**
     * Refuse to approve or pay a payout whose underlying orders stopped
     * qualifying after the request was made.
     */
    private function assertCommissionsStillPayable(MarketerPayout $payout): void
    {
        $orderIds = MarketerCommission::where('payout_id', $payout->id)
            ->where('status', '!=', MarketerCommission::STATUS_VOID)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            throw new RuntimeException('This payout has no valid commissions left.');
        }

        $disqualified = Order::withoutGlobalScope(ZoneScope::class)
            ->whereIn('id', $orderIds)
            ->where(function ($query) {
                $query->where('order_status', '!=', 'delivered')
                    ->orWhereNull('order_status');
            })
            ->exists();

        if ($disqualified) {
            throw new RuntimeException('This payout contains an order that is no longer delivered.');
        }

        $refunded = OrderTransaction::withoutGlobalScope(ZoneScope::class)
            ->whereIn('order_id', $orderIds)
            ->whereIn('status', self::VOID_TRANSACTION_STATUSES)
            ->exists();

        if ($refunded) {
            throw new RuntimeException('This payout contains a refunded order.');
        }
    }

    /**
     * Recalculate a payout after one of its commissions was voided. If nothing
     * payable remains, the payout is rejected rather than left for an admin to
     * pay a stale amount.
     */
    private function reconcilePayout(MarketerPayout $payout): void
    {
        if (in_array($payout->status, [MarketerPayout::STATUS_PAID, MarketerPayout::STATUS_REJECTED], true)) {
            return;
        }

        $remaining = MarketerCommission::where('payout_id', $payout->id)
            ->where('status', MarketerCommission::STATUS_IN_PAYOUT)
            ->get();

        if ($remaining->isEmpty()) {
            $payout->status = MarketerPayout::STATUS_REJECTED;
            $payout->rejection_reason = 'All commissions in this payout were voided.';
            $payout->save();

            return;
        }

        $payout->amount = round((float) $remaining->sum('amount'), 2);
        $payout->save();
    }

    /**
     * Balances for a marketer's portal.
     *
     * @return array<string, mixed>
     */
    public function summaryFor(Admin $marketer): array
    {
        $rows = MarketerCommission::where('marketer_id', $marketer->id)
            ->selectRaw('status, COUNT(*) as orders, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $value = fn (string $status) => (float) ($rows[$status]->total ?? 0);
        $count = fn (string $status) => (int) ($rows[$status]->orders ?? 0);

        $nextEligible = MarketerCommission::where('marketer_id', $marketer->id)
            ->where('status', MarketerCommission::STATUS_PENDING)
            ->whereNotNull('eligible_at')
            ->min('eligible_at');

        return [
            'pending' => $value(MarketerCommission::STATUS_PENDING),
            'pending_count' => $count(MarketerCommission::STATUS_PENDING),
            'eligible' => $value(MarketerCommission::STATUS_ELIGIBLE),
            'eligible_count' => $count(MarketerCommission::STATUS_ELIGIBLE),
            'in_payout' => $value(MarketerCommission::STATUS_IN_PAYOUT),
            'in_payout_count' => $count(MarketerCommission::STATUS_IN_PAYOUT),
            'paid' => $value(MarketerCommission::STATUS_PAID),
            'paid_count' => $count(MarketerCommission::STATUS_PAID),
            'void' => $value(MarketerCommission::STATUS_VOID),
            'void_count' => $count(MarketerCommission::STATUS_VOID),
            'next_eligible_at' => $nextEligible,
            'stores' => Store::withoutGlobalScope(ZoneScope::class)
                ->where('marketer_id', $marketer->id)
                ->count(),
            'currency' => $this->currency(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function orderIdsWithStatus(array $statuses)
    {
        return Order::withoutGlobalScope(ZoneScope::class)
            ->whereIn('order_status', $statuses)
            ->pluck('id');
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function refundedTransactionOrderIds()
    {
        return OrderTransaction::withoutGlobalScope(ZoneScope::class)
            ->whereIn('status', self::VOID_TRANSACTION_STATUSES)
            ->pluck('order_id');
    }
}
