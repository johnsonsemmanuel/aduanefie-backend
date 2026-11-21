<?php

namespace App\Http\Controllers;

use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Services\GroupBuy\GroupBuyService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-facing group buying web layer.
 *
 * Mirrors the API controller but renders server-side views so the group buy
 * experience feels like part of the main site rather than a separate mobile
 * silo. Every state change (join, pay, cancel) triggers email/SMS via
 * GroupBuyNotificationService so the group stays interconnected.
 */
class CustomerGroupBuyController extends Controller
{
    public function __construct(private GroupBuyService $groups)
    {
    }

    public function index(Request $request)
    {
        $query = GroupBuy::with(['item:id,name,price,image', 'store:id,name'])
            ->withCount('participants')
            ->where('status', GroupBuy::STATUS_OPEN)
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', (int) $request->store_id))
            ->orderByDesc('id');

        $groups = $query->paginate(12)->withQueryString();

        return view('customer.group-buys.index', compact('groups'));
    }

    public function show(Request $request, $id)
    {
        $group = GroupBuy::with(['item', 'store', 'participants.user', 'events'])->find($id);
        if (! $group) {
            abort(404);
        }

        $userId = Auth::guard('web')->check() ? (int) Auth::guard('web')->user()->id : null;
        $participant = $userId
            ? $group->participants->first(fn ($p) => (int) $p->user_id === $userId)
            : null;

        $unitPrice = $this->groups->resolvedUnitPrice($group);
        $memberShare = round($unitPrice * $group->share_units, 2);
        $shareLinks = $this->groups->shareLink($group);

        return view('customer.group-buys.show', compact(
            'group', 'participant', 'unitPrice', 'memberShare', 'userId', 'shareLinks'
        ));
    }

    public function join(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            abort(404);
        }
        if (! $group->isOpen()) {
            Toastr::error('This group is no longer accepting members.');
            return back();
        }

        $request->validate([
            'consent_accepted' => 'required|accepted',
            'delivery_address' => 'nullable|array',
            'delivery_address.street' => 'nullable|string|max:255',
            'delivery_address.city' => 'nullable|string|max:100',
            'delivery_address.postal_code' => 'nullable|string|max:20',
            'delivery_address.country' => 'nullable|string|size:2',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
        ]);

        $user = Auth::guard('web')->user();

        try {
            $this->groups->join(
                $group,
                $user ? (int) $user->id : null,
                $user ? null : $request->input('guest_id'),
                [
                    'consent_accepted' => (bool) $request->input('consent_accepted'),
                    'consent_version' => $group->consent_version,
                    'delivery_address' => $request->input('delivery_address'),
                    'gps_lat' => $request->input('gps_lat'),
                    'gps_lng' => $request->input('gps_lng'),
                    'actor_type' => 'customer',
                    'actor_id' => $user ? (int) $user->id : null,
                ]
            );

            $fresh = $group->fresh();
            if ($fresh->status === GroupBuy::STATUS_LOCKED) {
                Toastr::success('Group is full! You can now checkout your share.');
                return redirect()->route('customer.group-buys.checkout', $fresh->id);
            }

            Toastr::success('You joined the group. No payment has been taken yet.');
            return redirect()->route('customer.group-buys.show', $fresh->id);
        } catch (\InvalidArgumentException $e) {
            Toastr::error($e->getMessage());
            return back();
        }
    }

    public function checkout(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            abort(404);
        }
        if ($group->status !== GroupBuy::STATUS_LOCKED) {
            Toastr::error('Checkout is only open when the group is full.');
            return back();
        }

        $user = Auth::guard('web')->user();
        if (! $user) {
            Toastr::error('Please sign in to checkout your share.');
            return redirect()->route('login');
        }

        $participant = $group->participants->first(fn ($p) => (int) $p->user_id === (int) $user->id);
        if (! $participant) {
            Toastr::error('You are not a member of this group.');
            return back();
        }
        if ($participant->payment_status !== 'not_charged') {
            Toastr::info('You have already checked out your share.');
            return redirect()->route('customer.group-buys.show', $group->id);
        }

        $unitPrice = $this->groups->resolvedUnitPrice($group);

        return view('customer.group-buys.checkout', compact('group', 'participant', 'unitPrice'));
    }

    public function my(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $groups = GroupBuy::with(['item:id,name,price', 'store:id:name'])
            ->withCount('participants')
            ->whereHas('participants', fn ($q) => $q->where('user_id', (int) $user->id))
            ->orWhere('organizer_id', (int) $user->id)
            ->orderByDesc('id')
            ->get();

        return view('customer.group-buys.my', compact('groups'));
    }

    public function leave(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            abort(404);
        }

        $user = Auth::guard('web')->user();
        if (! $user) {
            Toastr::error('Please sign in to leave a group.');
            return back();
        }

        $participant = $group->participants->first(fn ($p) => (int) $p->user_id === (int) $user->id);
        if (! $participant) {
            Toastr::error('You are not a member of this group.');
            return back();
        }

        try {
            $this->groups->leave($group, $participant);
            Toastr::success('You left the group. No payment was taken.');
        } catch (\InvalidArgumentException $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }
}
