<?php

namespace App\Services\GroupBuy;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Trust tier for group organisers.
 *
 * This is a badge, not a creation gate: an unverified account may still open a
 * group. "Legit" means at least two delivered orders from other suppliers plus
 * a Ghana Card reference that an admin has reviewed. Only the last four
 * characters of the card are kept here; the full number is never stored.
 */
final class OrganizerTrustService
{
    /** Per-request cache so a listing of groups does not re-count per card. */
    private array $orderCounts = [];

    public const REQUIRED_ORDERS = 2;

    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public function summary(?User $user): array
    {
        $status = $user ? (string) ($user->organizer_verification_status ?: self::STATUS_UNVERIFIED) : self::STATUS_UNVERIFIED;
        $orders = $user ? $this->completedExternalOrders($user) : 0;
        $hasCard = (bool) ($user && $user->ghana_card_last4);
        $verified = (bool) ($user
            && $status === self::STATUS_VERIFIED
            && $hasCard
            && $orders >= self::REQUIRED_ORDERS
            && $user->organizer_verified_at);

        return [
            'verified' => $verified,
            'badge' => $verified ? 'verified_organizer' : null,
            'badge_label' => $verified ? 'Verified Organiser' : null,
            'status' => $status,
            'completed_external_orders' => $orders,
            'required_orders' => self::REQUIRED_ORDERS,
            'orders_met' => $orders >= self::REQUIRED_ORDERS,
            'ghana_card_submitted' => $hasCard,
            'ghana_card_last4' => $hasCard ? (string) $user->ghana_card_last4 : null,
            'submitted_at' => $user?->organizer_verification_submitted_at,
            'verified_at' => $verified ? $user->organizer_verified_at : null,
            'note' => $user?->organizer_verification_note,
        ];
    }

    /**
     * Safe to embed in public group listings: no card reference, no review note.
     */
    public function publicSummary(?User $user): array
    {
        $summary = $this->summary($user);

        return [
            'verified' => $summary['verified'],
            'badge' => $summary['badge'],
            'badge_label' => $summary['badge_label'],
            'orders_met' => $summary['orders_met'],
        ];
    }

    /**
     * Delivered marketplace orders from suppliers other than the buyer's own
     * store. Group-buy shares are excluded so a self-filled group cannot be
     * used to earn the badge.
     */
    public function completedExternalOrders(User $user): int
    {
        if (array_key_exists($user->id, $this->orderCounts)) {
            return $this->orderCounts[$user->id];
        }

        if (! Schema::hasTable('orders')) {
            return $this->orderCounts[$user->id] = 0;
        }

        $columns = Schema::getColumnListing('orders');
        if (! in_array('user_id', $columns, true) || ! in_array('order_status', $columns, true)) {
            return $this->orderCounts[$user->id] = 0;
        }

        $query = Order::query()
            ->where('user_id', $user->id)
            ->where('order_status', 'delivered');

        if (in_array('is_guest', $columns, true)) {
            $query->where('is_guest', 0);
        }
        if (in_array('group_buy_id', $columns, true)) {
            $query->whereNull('group_buy_id');
        }

        $ownVendorId = $user->userinfo?->vendor_id;
        if ($ownVendorId && Schema::hasTable('stores') && Schema::hasColumn('stores', 'vendor_id')) {
            $ownStoreIds = Store::where('vendor_id', $ownVendorId)->pluck('id');
            if ($ownStoreIds->isNotEmpty()) {
                $query->whereNotIn('store_id', $ownStoreIds);
            }
        }

        return $this->orderCounts[$user->id] = (int) $query->count();
    }
}
