<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Services\GroupBuy\GroupBuyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupBuyController extends Controller
{
    public function __construct(private GroupBuyService $groups)
    {
    }

    public function index(Request $request)
    {
        $query = GroupBuy::with(['item:id,name,price,image,module_id', 'store:id,name'])
            ->withCount('participants')
            ->when($request->filled('module_id'), fn ($q) => $q->where('module_id', (int) $request->module_id))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', (int) $request->store_id))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('country'), fn ($q) => $q->where('destination_country', strtoupper($request->country)))
            ->orderByDesc('id');

        return response()->json(['data' => $query->paginate(20)], 200);
    }

    public function show(Request $request, $id)
    {
        $group = GroupBuy::with(['item', 'store:id,name,country_code', 'participants', 'events'])
            ->find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        return response()->json([
            'data' => array_merge($group->toArray(), [
                'unit_price' => $this->groups->resolvedUnitPrice($group),
                'member_share_ghs' => round($this->groups->resolvedUnitPrice($group) * $group->share_units, 2),
                'spots_left' => max(0, $group->member_limit - $group->committed_count),
                'viewer' => $this->viewer($request, $group),
            ]),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer|exists:items,id',
            'target_value_ghs' => 'nullable|numeric|min:1',
            'member_limit' => 'nullable|integer|min:2|max:20',
            'destination_country' => 'nullable|string|size:2',
            'incoterm' => 'nullable|string|in:DDP,DAP,CIF,FOB,EXW',
            'closes_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        try {
            $group = $this->groups->create(array_merge($validator->validated(), [
                'module_id' => $request->header('moduleId'),
                'organizer_id' => $request->user ? (int) $request->user->id : null,
                'organizer_guest_id' => $request->user ? null : $request->input('guest_id'),
                'actor_type' => $request->user ? 'customer' : 'guest',
                'actor_id' => $request->user ? (int) $request->user->id : null,
            ]));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => $group->fresh()], 201);
    }

    public function join(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'guest_id' => 'nullable|string|max:64',
            'delivery_charge_ghs' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        try {
            $participant = $this->groups->join(
                $group,
                $request->user ? (int) $request->user->id : null,
                $request->user ? null : $request->input('guest_id'),
                $validator->validated()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'join', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json([
            'data' => [
                'participant' => $participant,
                'group' => $group->fresh(),
                'message' => $group->fresh()->status === GroupBuy::STATUS_LOCKED
                    ? 'Group complete. You can now check out your share.'
                    : 'You joined the group. No payment has been taken yet.',
            ],
        ], 200);
    }

    public function leave(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        $participant = $this->participantFor($request, $group);
        if (! $participant) {
            return response()->json(['errors' => [['code' => 'join', 'message' => 'You are not a member of this group.']]], 404);
        }

        try {
            $this->groups->leave($group, $participant);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'leave', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => ['message' => 'You left the group. No payment was taken.']], 200);
    }

    public function cancel(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        $isOrganizer = $request->user
            && (int) $group->organizer_id === (int) $request->user->id;
        if (! $isOrganizer) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only the organiser can cancel this group.']]], 403);
        }

        try {
            $this->groups->cancel($group);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'cancel', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => ['message' => 'Group cancelled. All members were released.']], 200);
    }

    public function quote(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        try {
            $quote = $this->groups->quote($group, $request->input('destination_country'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'quote', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => $quote], 200);
    }

    public function my(Request $request)
    {
        $userId = $request->user ? (int) $request->user->id : null;
        $guestId = $request->input('guest_id');

        $ids = GroupBuyParticipant::query()
            ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
            ->when($guestId, fn ($q) => $q->orWhere('guest_id', $guestId))
            ->pluck('group_buy_id')
            ->merge(GroupBuy::where('organizer_id', $userId)->pluck('id'))
            ->unique()
            ->values();

        $groups = GroupBuy::with(['item:id,name,price', 'store:id,name'])
            ->withCount('participants')
            ->whereIn('id', $ids)
            ->orderByDesc('id')
            ->get()
            ->map(function (GroupBuy $group) use ($userId, $guestId) {
                $member = $group->participants->first(function (GroupBuyParticipant $p) use ($userId, $guestId) {
                    return ($userId && (int) $p->user_id === $userId) || ($guestId && $p->guest_id === $guestId);
                });
                $group->my_participant = $member;
                return $group;
            });

        return response()->json(['data' => $groups], 200);
    }

    private function participantFor(Request $request, GroupBuy $group): ?GroupBuyParticipant
    {
        $userId = $request->user ? (int) $request->user->id : null;
        $guestId = $request->input('guest_id');

        if (! $userId && ! $guestId) {
            // Never fall through to an unscoped lookup: that would expose an
            // arbitrary member's share to an unidentified caller.
            return null;
        }

        return GroupBuyParticipant::where('group_buy_id', $group->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId && $guestId, fn ($q) => $q->where('guest_id', $guestId))
            ->first();
    }

    private function viewer(Request $request, GroupBuy $group): ?array
    {
        $participant = $this->participantFor($request, $group);
        if (! $participant) {
            return null;
        }

        return [
            'participant_id' => $participant->id,
            'item_share_ghs' => $participant->item_share_ghs,
            'share_units' => $participant->share_units,
            'payment_status' => $participant->payment_status,
            'fulfillment_status' => $participant->fulfillment_status,
        ];
    }
}
