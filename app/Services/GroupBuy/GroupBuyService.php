<?php

namespace App\Services\GroupBuy;

use App\Models\GroupBuy;
use App\Models\GroupBuyEvent;
use App\Models\GroupBuyParticipant;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Services\CrossBorder\ShippingQuoteService;
use Illuminate\Support\Facades\DB;

/**
 * Domain rules for shared group buying.
 *
 * Money rules that must never be relaxed:
 *  - The target value is configurable; 2000 GHS is only the suggested example.
 *  - Prices and shares are always derived here from the catalogue, never from
 *    a client payload.
 *  - No payment is captured at join time. A group that fails to fill releases
 *    its members without taking money, so "auto refund" means "never charged".
 *  - Every state transition writes a matching event, including failure paths.
 */
class GroupBuyService
{
    public function __construct(private ShippingQuoteService $quotes)
    {
    }

    /**
     * Create a group. The farmer price is snapshotted so later catalogue edits
     * cannot silently change an in-flight group's bill.
     */
    public function create(array $data): GroupBuy
    {
        $item = Item::withoutGlobalScopes()->find($data['item_id'] ?? null);
        if (! $item || (float) $item->price <= 0) {
            throw new \InvalidArgumentException('This item is not available for group buying.');
        }

        $farmerPrice = round((float) $item->price, 2);
        $targetValue = round((float) ($data['target_value_ghs'] ?? 2000), 2);
        if ($targetValue <= 0) {
            throw new \InvalidArgumentException('The group target value must be greater than zero.');
        }

        $memberLimit = max(2, min(20, (int) ($data['member_limit'] ?? 5)));

        // Round the pooled unit count up to a whole multiple of the member
        // limit so every member receives the exact same integer number of units.
        $rawUnits = (int) ceil($targetValue / $farmerPrice);
        $rawUnits = max($rawUnits, $memberLimit);
        $shareUnits = (int) ceil($rawUnits / $memberLimit);
        $targetUnits = $shareUnits * $memberLimit;

        $closesAt = ! empty($data['closes_at'])
            ? \Carbon\Carbon::parse($data['closes_at'])
            : now()->addDays(7);
        if ($closesAt->isPast()) {
            throw new \InvalidArgumentException('The group closing time must be in the future.');
        }

        $store = Store::find($item->store_id);
        $destination = ! empty($data['destination_country'])
            ? strtoupper((string) $data['destination_country'])
            : null;
        $isCrossBorder = $destination
            ? strtoupper($this->quotes->originCountry($store)) !== $destination
            : false;

        $group = GroupBuy::create([
            'module_id' => $data['module_id'] ?? $item->module_id,
            'store_id' => $item->store_id,
            'item_id' => $item->id,
            'organizer_id' => $data['organizer_id'] ?? null,
            'organizer_guest_id' => $data['organizer_guest_id'] ?? null,
            'status' => GroupBuy::STATUS_OPEN,
            'target_value_ghs' => $targetValue,
            'farmer_price_ghs' => $farmerPrice,
            'target_units' => $targetUnits,
            'share_units' => $shareUnits,
            'member_limit' => $memberLimit,
            'committed_count' => 0,
            'destination_country' => $destination,
            'is_cross_border' => $isCrossBorder,
            'incoterm' => $isCrossBorder ? strtoupper($data['incoterm'] ?? 'DDP') : null,
            'currency_code' => 'GHS',
            'closes_at' => $closesAt,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->log($group, 'created', null, $data['actor_type'] ?? null, $data['actor_id'] ?? null, [
            'target_value_ghs' => $targetValue,
            'target_units' => $targetUnits,
            'member_limit' => $memberLimit,
        ]);

        return $group;
    }

    /**
     * Best bulk tier for the pooled quantity. The client never supplies this.
     */
    public function resolvedUnitPrice(GroupBuy $group): float
    {
        $tiers = $group->item?->bulk_pricing_tiers;
        $best = null;

        if (is_array($tiers)) {
            foreach ($tiers as $tier) {
                $minQty = (int) ($tier['min_qty'] ?? 0);
                $price = (float) ($tier['unit_price'] ?? 0);
                if ($minQty > 0 && $price > 0 && $minQty <= $group->target_units) {
                    if ($best === null || $minQty > $best['min_qty']) {
                        $best = ['min_qty' => $minQty, 'unit_price' => $price];
                    }
                }
            }
        }

        return round($best['unit_price'] ?? (float) $group->farmer_price_ghs, 2);
    }

    /**
     * Join an open group. Locks the row so the 5th member cannot oversubscribe.
     */
    public function join(GroupBuy $group, ?int $userId, ?string $guestId, array $data = []): GroupBuyParticipant
    {
        if (! $userId && ! $guestId) {
            throw new \InvalidArgumentException('You must be signed in or provide a guest reference to join.');
        }

        return DB::transaction(function () use ($group, $userId, $guestId, $data) {
            /** @var GroupBuy $group */
            $group = GroupBuy::whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($group->status !== GroupBuy::STATUS_OPEN) {
                throw new \InvalidArgumentException('This group is no longer accepting members.');
            }
            if ($group->closes_at && $group->closes_at->isPast()) {
                throw new \InvalidArgumentException('This group has closed.');
            }
            if ($group->committed_count >= $group->member_limit) {
                throw new \InvalidArgumentException('This group is already full.');
            }

            $exists = GroupBuyParticipant::where('group_buy_id', $group->id)
                ->where(function ($q) use ($userId, $guestId) {
                    $q->when($userId, fn ($inner) => $inner->orWhere('user_id', $userId))
                      ->when($guestId, fn ($inner) => $inner->orWhere('guest_id', $guestId));
                })
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException('You have already joined this group.');
            }

            $unitPrice = $this->resolvedUnitPrice($group);
            $participant = GroupBuyParticipant::create([
                'group_buy_id' => $group->id,
                'user_id' => $userId,
                'guest_id' => $guestId,
                'share_units' => $group->share_units,
                'item_share_ghs' => round($unitPrice * $group->share_units, 2),
                'delivery_charge_ghs' => round((float) ($data['delivery_charge_ghs'] ?? 0), 2),
                'delivery_address' => $data['delivery_address'] ?? null,
                'payment_status' => GroupBuyParticipant::PAYMENT_NOT_CHARGED,
                'fulfillment_status' => GroupBuyParticipant::FULFILLMENT_PENDING,
                'joined_at' => now(),
            ]);

            $group->increment('committed_count');
            $this->log($group, 'joined', $participant->id, $data['actor_type'] ?? 'customer', $userId ?? null, [
                'committed_count' => $group->committed_count + 1,
            ]);

            if ($group->fresh()->committed_count >= $group->member_limit) {
                $group->update([
                    'status' => GroupBuy::STATUS_LOCKED,
                    'locked_at' => now(),
                ]);
                $this->log($group, 'locked', null, 'system', null, [
                    'committed_count' => $group->member_limit,
                ]);
            }

            return $participant;
        });
    }

    public function leave(GroupBuy $group, GroupBuyParticipant $participant): void
    {
        if ($group->status !== GroupBuy::STATUS_OPEN) {
            throw new \InvalidArgumentException('You can only leave a group that is still open.');
        }

        DB::transaction(function () use ($group, $participant) {
            $participant->delete();
            $group->decrement('committed_count');
            $this->log($group, 'left', $participant->id, 'customer', $participant->user_id, [
                'message' => 'Member left before the group filled; no payment was taken.',
            ]);
        });
    }

    /**
     * Release every member of a group that did not fill. No money was captured,
     * so there is nothing to refund; the event trail records that explicitly.
     */
    public function release(GroupBuy $group, string $status, string $event): void
    {
        if (! in_array($status, [GroupBuy::STATUS_CANCELLED, GroupBuy::STATUS_EXPIRED], true)) {
            throw new \InvalidArgumentException('Invalid release status.');
        }

        DB::transaction(function () use ($group, $status, $event) {
            /** @var GroupBuy $group */
            $group = GroupBuy::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->status !== GroupBuy::STATUS_OPEN) {
                return;
            }

            $participants = $group->participants()->get();
            foreach ($participants as $participant) {
                $participant->update([
                    'payment_status' => GroupBuyParticipant::PAYMENT_RELEASED,
                    'released_at' => now(),
                ]);
                $this->log($group, 'released', $participant->id, 'system', null, [
                    'reason' => $event,
                    'message' => 'No payment captured; nothing to refund.',
                ]);
            }

            $group->update(['status' => $status]);
            $this->log($group, $event, null, 'system', null, [
                'released_participants' => $participants->count(),
            ]);
        });
    }

    public function cancel(GroupBuy $group): void
    {
        $this->release($group, GroupBuy::STATUS_CANCELLED, 'cancelled');
    }

    public function expire(GroupBuy $group): void
    {
        $this->release($group, GroupBuy::STATUS_EXPIRED, 'expired');
    }

    /**
     * Called after the participant's own order has been committed. We record
     * "ordered", not "paid": the order's payment_status remains the source of
     * truth for whether money was actually captured.
     */
    public function markOrdered(GroupBuyParticipant $participant, int $orderId): void
    {
        $participant->update(['payment_status' => 'ordered']);
        $this->log($participant->groupBuy, 'order_placed', $participant->id, 'customer', $participant->user_id, [
            'order_id' => $orderId,
            'message' => 'Member checked out their 1/N share; delivery is charged on their own order.',
        ]);
    }

    public function markShipped(GroupBuyParticipant $participant): void
    {
        $participant->update(['fulfillment_status' => GroupBuyParticipant::FULFILLMENT_SHIPPED]);
        $this->log($participant->groupBuy, 'shipped', $participant->id, 'supplier', null, [
            'order_link' => 'supplier ships directly to this member',
        ]);
    }

    public function markDelivered(GroupBuyParticipant $participant): void
    {
        $participant->update(['fulfillment_status' => GroupBuyParticipant::FULFILLMENT_DELIVERED]);
        $this->log($participant->groupBuy, 'delivered', $participant->id, 'system', null);

        $group = $participant->groupBuy;
        $outstanding = $group->participants()
            ->where('fulfillment_status', '!=', GroupBuyParticipant::FULFILLMENT_DELIVERED)
            ->exists();
        if (! $outstanding && $group->status === GroupBuy::STATUS_LOCKED) {
            $group->update([
                'status' => GroupBuy::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);
            $this->log($group, 'fulfilled', null, 'system', null);
        }
    }

    /**
     * Checkout guard. Called by OrderController whenever group_buy_id is sent.
     * Any failure is a hard 403 — there is no retail-price fallback.
     *
     * @return array{group:GroupBuy,participant:GroupBuyParticipant,unit_price:float}
     */
    public function authorizeCheckout(int $groupId, ?int $userId, ?string $guestId): array
    {
        $group = GroupBuy::find($groupId);
        if (! $group) {
            throw new \InvalidArgumentException('This group buy no longer exists.');
        }
        if ($group->status !== GroupBuy::STATUS_LOCKED) {
            throw new \InvalidArgumentException(
                'This group is not complete yet. Checkout opens when all members have joined.'
            );
        }
        if (! $userId && ! $guestId) {
            // Without an identity the query below would match an arbitrary
            // member and let a stranger check out someone else's share.
            throw new \InvalidArgumentException('You must be signed in or provide a guest reference to check out.');
        }

        $participant = GroupBuyParticipant::where('group_buy_id', $group->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId && $guestId, fn ($q) => $q->where('guest_id', $guestId))
            ->first();

        if (! $participant) {
            throw new \InvalidArgumentException('You are not a member of this group buy.');
        }
        if (Order::where('group_buy_participant_id', $participant->id)->exists()) {
            throw new \InvalidArgumentException('This group share has already been checked out.');
        }

        return [
            'group' => $group,
            'participant' => $participant,
            'unit_price' => $this->resolvedUnitPrice($group),
        ];
    }

    /**
     * Freight/duty for the WHOLE pooled quantity, quoted from the catalogue.
     * Each member's own delivery is charged on their individual order.
     */
    public function quote(GroupBuy $group, ?string $destinationCountry = null): array
    {
        $store = $group->store;
        $destination = strtoupper($destinationCountry ?: $group->destination_country ?: '');
        if (! $destination) {
            throw new \InvalidArgumentException('A destination country is required for a group shipping quote.');
        }

        $item = $group->item;
        $lines = [[
            'item_id' => (int) $item->id,
            'name' => $item->name,
            'quantity' => $group->target_units,
            'unit_price' => $this->resolvedUnitPrice($group),
            'weight' => (float) ($item->weight_kg ?? 0),
            'hs_code' => $item->hs_code ?? null,
        ]];

        $quoted = $this->quotes->options($store, $destination, $lines, $group->currency_code ?: 'GHS');

        return [
            'group_buy_id' => $group->id,
            'pooled_units' => $group->target_units,
            'per_member_units' => $group->share_units,
            'per_member_item_share_ghs' => round($this->resolvedUnitPrice($group) * $group->share_units, 2),
            'quote' => $quoted,
        ];
    }

    private function log(
        GroupBuy $group,
        string $event,
        ?int $participantId,
        ?string $actorType = null,
        ?int $actorId = null,
        ?array $meta = null
    ): void {
        GroupBuyEvent::create([
            'group_buy_id' => $group->id,
            'participant_id' => $participantId,
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'meta' => $meta,
        ]);
    }
}
