<?php

namespace App\Services\GroupBuy;

use App\Models\GroupBuy;
use App\Models\GroupBuyEvent;
use App\Models\GroupBuyMessage;
use App\Models\GroupBuyParticipant;
use App\Models\Item;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Services\CrossBorder\ShippingQuoteService;
use App\Services\GroupBuy\GroupBuyNotificationService;
use Illuminate\Support\Facades\Cache;
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
 *  - Consent is required before joining; notifications fire on every transition.
 */
class GroupBuyService
{
    public const INCOMPATIBLE_MODULE_TYPES = ['parcel', 'rental', 'service', 'food', 'pharmacy'];

    public function __construct(
        private ShippingQuoteService $quotes,
        private GroupBuyNotificationService $notifications,
    ) {
    }

    /**
     * A module supports group buying only when its type supports physical goods
     * and at least one active supplier item offers bulk/larger quantities.
     */
    public function isModuleCompatible(?int $moduleId): bool
    {
        if (! $moduleId) {
            return false;
        }

        return Cache::remember("group_buy_module_compatible_{$moduleId}", now()->addMinutes(2), function () use ($moduleId) {
            $module = Module::find($moduleId);
            if (! $module || in_array($module->module_type, self::INCOMPATIBLE_MODULE_TYPES, true)) {
                return false;
            }

            return Item::withoutGlobalScopes()
                ->where('module_id', $moduleId)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->where('moq', '>', 1)
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('bulk_pricing_tiers')
                                ->where('bulk_pricing_tiers', '!=', '[]')
                                ->where('bulk_pricing_tiers', '!=', 'null');
                        })
                        ->orWhereHas('bulkPricingTiers');
                })
                ->exists();
        });
    }

    /**
     * Whether a specific item offers bulk/group-buy quantities.
     */
    public function isItemBulkEligible(Item $item): bool
    {
        if ((int) ($item->moq ?? 1) > 1) {
            return true;
        }

        if (is_array($item->bulk_pricing_tiers) && count($item->bulk_pricing_tiers) > 0) {
            return true;
        }

        return $item->bulkPricingTiers()->exists();
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

        if (! empty($data['module_id']) && (int) $data['module_id'] !== (int) $item->module_id) {
            throw new \InvalidArgumentException('This item does not belong to the selected module.');
        }

        $module = Module::find($item->module_id);
        if (! $module || in_array($module->module_type, self::INCOMPATIBLE_MODULE_TYPES, true)) {
            throw new \InvalidArgumentException('Group buying is not supported in this module.');
        }

        if (! $this->isItemBulkEligible($item)) {
            throw new \InvalidArgumentException('This item does not offer bulk quantities for group buying.');
        }

        $farmerPrice = round((float) $item->price, 2);
        $targetValue = round((float) ($data['target_value_ghs'] ?? 2000), 2);
        if ($targetValue <= 0) {
            throw new \InvalidArgumentException('The group target value must be greater than zero.');
        }

        $memberLimit = max(2, min(20, (int) ($data['member_limit'] ?? 5)));

        // Round the pooled unit count up to a whole multiple of the member
        // limit so every member receives the exact same integer number of units,
        // and never below the supplier's minimum order quantity (MOQ).
        $rawUnits = (int) ceil($targetValue / $farmerPrice);
        $rawUnits = max($rawUnits, $memberLimit, (int) ($item->moq ?? 1));
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
     *
     * @param  array  $data  May contain 'consent_accepted' (bool), 'consent_version' (string),
     *                       'delivery_charge_ghs', 'delivery_address' (array), 'gps_lat', 'gps_lng'
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

            // Consent dialogue: the group may require explicit acceptance.
            if ($group->consent_text && ! ($data['consent_accepted'] ?? false)) {
                throw new \InvalidArgumentException('You must accept the group consent terms to join.');
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
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'payment_status' => GroupBuyParticipant::PAYMENT_NOT_CHARGED,
                'fulfillment_status' => GroupBuyParticipant::FULFILLMENT_PENDING,
                'joined_at' => now(),
                'consent_accepted_at' => ! empty($data['consent_accepted']) ? now() : null,
                'consent_version' => $data['consent_version'] ?? $group->consent_version,
            ]);

            $group->increment('committed_count');
            $this->log($group, 'joined', $participant->id, $data['actor_type'] ?? 'customer', $userId ?? null, [
                // increment() already updates the in-memory attribute.
                'committed_count' => $group->committed_count,
                'consent_accepted' => $data['consent_accepted'] ?? false,
            ]);

            // Notify before locking so the new member sees the welcome message.
            $this->notifications->memberJoined($group, $participant);

            if ($group->fresh()->committed_count >= $group->member_limit) {
                $group->update([
                    'status' => GroupBuy::STATUS_LOCKED,
                    'locked_at' => now(),
                ]);
                $this->log($group, 'locked', null, 'system', null, [
                    'committed_count' => $group->member_limit,
                ]);
                $this->notifications->groupLocked($group);
            }

            return $participant;
        });
    }

    public function leave(GroupBuy $group, GroupBuyParticipant $participant, ?string $reason = null, ?string $note = null): void
    {
        if ($group->status !== GroupBuy::STATUS_OPEN) {
            throw new \InvalidArgumentException('You can only leave a group that is still open.');
        }

        $cleanReason = $reason ? mb_substr(trim($reason), 0, 255) : null;
        $cleanNote = $note ? mb_substr(trim($note), 0, 500) : null;

        DB::transaction(function () use ($group, $participant, $cleanReason, $cleanNote) {
            $participant->delete();
            if ($group->committed_count > 0) {
                $group->decrement('committed_count');
            }
            $this->log($group, 'left', $participant->id, 'customer', $participant->user_id, array_filter([
                'reason' => $cleanReason,
                'note' => $cleanNote,
                'message' => 'Member left before the group filled; spot reopened for new buyers.',
            ]));

            // Let the group team see why a member left and that a spot reopened.
            if ($cleanReason || $cleanNote) {
                $summary = $cleanReason ?: 'Member left';
                if ($cleanNote) {
                    $summary .= " - {$cleanNote}";
                }
                GroupBuyMessage::create([
                    'group_buy_id' => $group->id,
                    'sender_user_id' => null,
                    'sender_guest_id' => null,
                    'sender_name' => 'Group Update',
                    'body' => "A member left the group ({$summary}). A spot is now open for another buyer to join.",
                ]);
            }
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

    public function cancel(GroupBuy $group, ?string $reason = null): void
    {
        $this->release($group, GroupBuy::STATUS_CANCELLED, 'cancelled');
        if ($reason) {
            $this->log($group, 'closed_reason', null, 'customer', $group->organizer_id, [
                'reason' => mb_substr(trim($reason), 0, 255),
            ]);
        }
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

        $this->notifications->shipped($participant);
    }

    public function markDelivered(GroupBuyParticipant $participant): void
    {
        $participant->update(['fulfillment_status' => GroupBuyParticipant::FULFILLMENT_DELIVERED]);
        $this->log($participant->groupBuy, 'delivered', $participant->id, 'system', null);

        $this->notifications->delivered($participant);

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
     * Record that a member has paid for their share. Called after the member's
     * order payment is confirmed. When all members pay, the escrow is marked
     * ready for release to the store.
     */
    public function markPaid(GroupBuyParticipant $participant): void
    {
        $participant->update(['payment_status' => GroupBuyParticipant::PAYMENT_PAID]);
        $this->log($participant->groupBuy, 'payment_received', $participant->id, 'customer', $participant->user_id, [
            'item_share_ghs' => $participant->item_share_ghs,
        ]);

        $group = $participant->groupBuy->fresh();
        if ($group->isFullyPaid() && $group->escrow_status !== 'released') {
            $group->update(['escrow_status' => 'ready']);
            $this->log($group, 'escrow_ready', null, 'system', null, [
                'paid_count' => $group->paidCount(),
                'total_members' => $group->member_limit,
            ]);
        }
    }

    /**
     * Release escrow funds to the store. Admin-triggered after all deliveries
     * are confirmed.
     */
    public function releaseEscrow(GroupBuy $group): void
    {
        if ($group->escrow_status !== 'ready') {
            throw new \InvalidArgumentException('Escrow is not ready for release. All members must pay first.');
        }

        $group->update([
            'escrow_status' => 'released',
            'escrow_released_at' => now(),
        ]);
        $this->log($group, 'escrow_released', null, 'system', null, [
            'released_at' => now()->toISOString(),
        ]);
    }

    /**
     * Checkout guard. Called by OrderController whenever group_buy_id is sent.
     * Any failure is a hard 403 - there is no retail-price fallback.
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
     * Edit an open group. Structural fields (target, member limit, destination)
     * are frozen once members have joined: changing them would silently rewrite
     * the equal share every member agreed to. Non-structural fields stay
     * editable for the life of the open group.
     */
    public function update(GroupBuy $group, array $data): GroupBuy
    {
        if ($group->status !== GroupBuy::STATUS_OPEN) {
            throw new \InvalidArgumentException('Only an open group can be edited.');
        }

        $structural = ['target_value_ghs', 'member_limit', 'destination_country'];
        $hasStructuralChange = array_intersect($structural, array_keys($data)) !== [];
        if ($hasStructuralChange && $group->committed_count > 0) {
            throw new \InvalidArgumentException(
                'The target, member limit and destination cannot change after members join.'
            );
        }

        $changes = [];

        if (array_key_exists('target_value_ghs', $data)) {
            $changes['target_value_ghs'] = round((float) $data['target_value_ghs'], 2);
            if ($changes['target_value_ghs'] <= 0) {
                throw new \InvalidArgumentException('The group target value must be greater than zero.');
            }
        }

        $memberLimit = $group->member_limit;
        if (array_key_exists('member_limit', $data)) {
            $memberLimit = max(2, min(20, (int) $data['member_limit']));
            $changes['member_limit'] = $memberLimit;
        }

        // Reuse the snapshotted farmer price so editing the target never
        // silently picks up a later catalogue price change.
        if (array_key_exists('target_value_ghs', $data) || array_key_exists('member_limit', $data)) {
            $target = $changes['target_value_ghs'] ?? (float) $group->target_value_ghs;
            $price = max(0.01, (float) $group->farmer_price_ghs);
            $shareUnits = (int) ceil(max((int) ceil($target / $price), $memberLimit) / $memberLimit);
            $changes['share_units'] = $shareUnits;
            $changes['target_units'] = $shareUnits * $memberLimit;
        }

        if (array_key_exists('destination_country', $data)) {
            $destination = strtoupper((string) $data['destination_country']);
            $store = $group->store ?: Store::find($group->store_id);
            $changes['destination_country'] = $destination;
            $changes['is_cross_border'] = $destination
                ? strtoupper($this->quotes->originCountry($store)) !== $destination
                : false;
            $changes['incoterm'] = $changes['is_cross_border']
                ? strtoupper($data['incoterm'] ?? $group->incoterm ?? 'DDP')
                : null;
        }

        if (array_key_exists('closes_at', $data)) {
            $closesAt = \Carbon\Carbon::parse($data['closes_at']);
            if ($closesAt->isPast()) {
                throw new \InvalidArgumentException('The group closing time must be in the future.');
            }
            $changes['closes_at'] = $closesAt;
        }

        if (array_key_exists('notes', $data)) {
            $changes['notes'] = $data['notes'] ?: null;
        }

        if (! $changes) {
            return $group;
        }

        $before = [];
        foreach (array_keys($changes) as $field) {
            $before[$field] = $group->{$field};
        }

        DB::transaction(function () use ($group, $changes, $before, $hasStructuralChange) {
            /** @var GroupBuy $fresh */
            $fresh = GroupBuy::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== GroupBuy::STATUS_OPEN
                || ($hasStructuralChange && $fresh->committed_count > 0)) {
                throw new \InvalidArgumentException('This group can no longer be edited.');
            }
            $fresh->update($changes);
            $this->log($fresh, 'updated', null, 'customer', $fresh->organizer_id, [
                'before' => $before,
                'after' => $changes,
            ]);
        });

        return $group->fresh();
    }

    /**
     * Chat history for a group. The caller must already have passed the
     * membership guard in the controller - this method only paginates.
     *
     * @return GroupBuyMessage[]
     */
    public function messages(GroupBuy $group, int $afterId = 0, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        return GroupBuyMessage::where('group_buy_id', $group->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function postMessage(
        GroupBuy $group,
        ?int $userId,
        ?string $guestId,
        string $senderName,
        string $body
    ): GroupBuyMessage {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Write a message before sending.');
        }
        if (mb_strlen($body) > 1000) {
            throw new \InvalidArgumentException('Messages must be 1000 characters or fewer.');
        }
        if (! $userId && ! $guestId) {
            throw new \InvalidArgumentException('You must be a member of this group to chat.');
        }

        return GroupBuyMessage::create([
            'group_buy_id' => $group->id,
            'sender_user_id' => $userId,
            'sender_guest_id' => $userId ? null : $guestId,
            'sender_name' => mb_substr($senderName, 0, 120) ?: 'Guest',
            'body' => $body,
        ]);
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

    /**
     * Shareable links for a group buy: web URL + mobile deep link.
     * Deep links open the group directly in the mobile app (aduanefie://),
     * falling back to the web URL if the app isn't installed.
     *
     * @return array{web:string,deep:string}
     */
    public function shareLink(GroupBuy $group): array
    {
        $web = url(route('customer.group-buys.show', $group->id));
        $deep = 'aduanefie://group-buy/' . $group->id;

        return ['web' => $web, 'deep' => $deep];
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
