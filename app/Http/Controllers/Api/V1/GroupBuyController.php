<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Services\GroupBuy\GroupBuyInviteService;
use App\Services\GroupBuy\GroupBuyService;
use App\Services\GroupBuy\OrganizerTrustService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupBuyController extends Controller
{
    public function __construct(
        private GroupBuyService $groups,
        private OrganizerTrustService $trust,
        private GroupBuyInviteService $invites,
    ) {
    }

    public function index(Request $request)
    {
        $query = GroupBuy::with([
            'item:id,name,price,image,module_id',
            'store:id,name',
            'organizer:id,f_name,l_name,image,organizer_verification_status,ghana_card_last4,organizer_verified_at,organizer_verification_note',
        ])
            ->withCount('participants')
            ->when($request->filled('module_id'), fn ($q) => $q->where('module_id', (int) $request->module_id))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', (int) $request->store_id))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('country'), fn ($q) => $q->where('destination_country', strtoupper($request->country)))
            ->orderByDesc('id');

        $groups = $query->paginate(20);
        $groups->getCollection()->transform(function (GroupBuy $group) {
            // Public listing: only the badge, never the card reference or note.
            $group->organizer_trust = $this->trust->publicSummary($group->organizer);

            return $group;
        });

        return response()->json(['data' => $groups], 200);
    }

    public function show(Request $request, $id)
    {
        $group = GroupBuy::with([
            'item',
            'store:id,name,country_code',
            'participants',
            'events',
            'organizer:id,f_name,l_name,image,organizer_verification_status,ghana_card_last4,organizer_verified_at,organizer_verification_note',
        ])->find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        return response()->json([
            'data' => array_merge($group->toArray(), [
                'unit_price' => $this->groups->resolvedUnitPrice($group),
                'member_share_ghs' => round($this->groups->resolvedUnitPrice($group) * $group->share_units, 2),
                'spots_left' => max(0, $group->member_limit - $group->committed_count),
                'viewer' => $this->viewer($request, $group),
                'organizer_trust' => $this->trust->publicSummary($group->organizer),
                // Explicit flags: the UI must never infer editing rights from
                // being on the "my groups" tab, which also lists joined groups.
                'is_organizer' => $this->isOrganizer($request, $group),
                'can_edit' => $this->isOrganizer($request, $group)
                    && $group->status === GroupBuy::STATUS_OPEN,
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

        $identity = $this->identity($request);
        if (! $identity['user_id'] && ! $identity['guest_id']) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'A sign-in or guest reference is required.']]], 403);
        }

        try {
            $group = $this->groups->create(array_merge($validator->validated(), [
                'module_id' => $request->header('moduleId'),
                'organizer_id' => $identity['user_id'],
                'organizer_guest_id' => $identity['guest_id'],
                'actor_type' => $identity['user_id'] ? 'customer' : 'guest',
                'actor_id' => $identity['user_id'],
            ]));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => $group->fresh()], 201);
    }

    /**
     * Own verification state. The full card number is never accepted here and
     * the stored last four are only returned to the account owner.
     */
    public function verification(Request $request)
    {
        return response()->json(['data' => $this->trust->summary(auth('api')->user())], 200);
    }

    public function submitVerification(Request $request)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Sign in to submit verification.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'ghana_card_last4' => 'required|string|size:4|regex:/^[A-Za-z0-9]{4}$/',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $user->update([
            'ghana_card_last4' => strtoupper((string) $request->ghana_card_last4),
            'organizer_verification_status' => OrganizerTrustService::STATUS_PENDING,
            'organizer_verification_submitted_at' => now(),
            'organizer_verified_at' => null,
            'organizer_verification_note' => null,
        ]);

        return response()->json([
            'data' => $this->trust->summary($user->fresh()),
            'message' => 'Ghana Card reference submitted for review.',
        ], 200);
    }

    /**
     * Refer-a-friend invite. Only a signed-in organiser or member may send one,
     * and the address is supplied explicitly by the sender.
     */
    public function invite(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        $identity = $this->identity($request);
        if (! $identity['user_id'] || ! $this->isInsider($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only signed-in group members can invite a friend.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'message' => 'nullable|string|max:300',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        try {
            $invite = $this->invites->send(
                $group,
                auth('api')->user(),
                (string) $request->email,
                $request->message
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'errors' => [['code' => 'invite', 'message' => $e->getMessage()]],
            ], (int) ($e->getCode() ?: 500));
        }

        return response()->json([
            'data' => $invite,
            'message' => 'Invite sent to ' . $invite->email . '.',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }
        if (! $this->isOrganizer($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only the organiser can edit this group.']]], 403);
        }

        $validator = Validator::make($request->all(), [
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
            $updated = $this->groups->update($group, $validator->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'update', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => $updated, 'message' => 'Group details updated.']);
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

        $identity = $this->identity($request);
        try {
            $participant = $this->groups->join(
                $group,
                $identity['user_id'],
                $identity['guest_id'],
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

        $reason = trim((string) $request->input('reason', ''));
        $note = trim((string) $request->input('note', ''));

        try {
            $this->groups->leave($group, $participant, $reason ?: null, $note ?: null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'leave', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json([
            'data' => [
                'message' => 'You left the group. Your spot is now open for another buyer, and no payment was taken.',
            ],
        ], 200);
    }

    public function cancel(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }

        if (! $this->isOrganizer($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only the organiser can close this group.']]], 403);
        }

        $reason = trim((string) $request->input('reason', ''));

        try {
            $this->groups->cancel($group, $reason ?: null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'cancel', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => ['message' => 'Group closed. All members were released with no charge.']], 200);
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
        $identity = $this->identity($request);
        $userId = $identity['user_id'];
        $guestId = $identity['guest_id'];

        // With no identity the old query fell through to "every participant",
        // leaking all groups. Fail closed instead.
        if (! $userId && ! $guestId) {
            return response()->json(['data' => []], 200);
        }

        $ids = GroupBuyParticipant::query()
            ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
            ->when($guestId, fn ($q) => $q->orWhere('guest_id', $guestId))
            ->pluck('group_buy_id')
            ->merge(GroupBuy::where('organizer_id', $userId)->pluck('id'))
            ->when($guestId, fn ($collection) => $collection->merge(
                GroupBuy::where('organizer_guest_id', $guestId)->pluck('id')
            ))
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
                $group->is_organizer = $userId
                    ? (int) $group->organizer_id === $userId
                    : (bool) ($guestId && $group->organizer_guest_id === $guestId);
                return $group;
            });

        return response()->json(['data' => $groups], 200);
    }

    /**
     * Everyone in the group: the organiser plus every participant. Delivery
     * addresses are deliberately excluded - members do not need to see each
     * other's private address, and the supplier ships to each member directly.
     */
    public function members(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }
        if (! $this->isInsider($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only members can see the group list.']]], 403);
        }

        $identity = $this->identity($request);
        $rows = $group->participants()
            ->with('user:id,f_name,l_name,image,organizer_verification_status,ghana_card_last4,organizer_verified_at,organizer_verification_note')
            ->orderBy('id')
            ->get()
            ->map(function (GroupBuyParticipant $p) use ($group, $identity) {
                $isOrganizer = $identity['user_id']
                    ? (int) $group->organizer_id === $identity['user_id']
                    : (bool) ($identity['guest_id'] && $group->organizer_guest_id === $identity['guest_id']);

                return [
                    'participant_id' => $p->id,
                    'name' => $p->user?->full_name ?: ($p->guest_id ? 'Guest member' : 'Member'),
                    'role' => $this->isParticipantOrganizer($group, $p) ? 'organiser' : 'member',
                    'share_units' => $p->share_units,
                    'item_share_ghs' => $p->item_share_ghs,
                    'payment_status' => $p->payment_status,
                    'fulfillment_status' => $p->fulfillment_status,
                    'joined_at' => $p->joined_at,
                    'is_self' => $identity['user_id']
                        ? (int) $p->user_id === $identity['user_id']
                        : (bool) ($identity['guest_id'] && $p->guest_id === $identity['guest_id']),
                    'is_organizer' => $isOrganizer,
                    // Badge only: no card reference is exposed to other members.
                    'trust_badge' => $this->trust->publicSummary($p->user)['badge'],
                ];
            });

        // The organiser often has no participant row of their own.
        $organizerIsMember = $group->participants->contains(function (GroupBuyParticipant $p) use ($group) {
            return $this->isParticipantOrganizer($group, $p);
        });
        if (! $organizerIsMember) {
            $organizer = $group->organizer;
            $rows->prepend([
                'participant_id' => null,
                'name' => $organizer?->full_name ?: 'Group organiser',
                'role' => 'organiser',
                'share_units' => null,
                'item_share_ghs' => null,
                'payment_status' => null,
                'fulfillment_status' => null,
                'joined_at' => null,
                'is_self' => (bool) ($identity['user_id'] && (int) $group->organizer_id === $identity['user_id']),
                'is_organizer' => true,
                'trust_badge' => $this->trust->publicSummary($organizer)['badge'],
            ]);
        }

        return response()->json(['data' => $rows->values()], 200);
    }

    public function messages(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }
        if (! $this->isInsider($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only members can read this chat.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'after' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $identity = $this->identity($request);
        $messages = $this->groups->messages(
            $group,
            (int) $request->input('after', 0),
            (int) $request->input('limit', 50)
        );

        $rows = array_map(function ($message) use ($identity) {
            return [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'body' => $message->body,
                'created_at' => $message->created_at,
                'is_self' => $identity['user_id']
                    ? (int) $message->sender_user_id === $identity['user_id']
                    : (bool) ($identity['guest_id'] && $message->sender_guest_id === $identity['guest_id']),
            ];
        }, $messages);

        $lastId = end($rows);
        return response()->json([
            'data' => $rows,
            'next_after' => $lastId ? $lastId['id'] : (int) $request->input('after', 0),
        ], 200);
    }

    public function sendMessage(Request $request, $id)
    {
        $group = GroupBuy::find($id);
        if (! $group) {
            return response()->json(['errors' => [['code' => 'group_buy', 'message' => 'Group buy not found.']]], 404);
        }
        if (! $this->isInsider($request, $group)) {
            return response()->json(['errors' => [['code' => 'permission', 'message' => 'Only members can post in this chat.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $identity = $this->identity($request);
        try {
            $message = $this->groups->postMessage(
                $group,
                $identity['user_id'],
                $identity['guest_id'],
                $identity['name'],
                $validator->validated()['message']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['code' => 'message', 'message' => $e->getMessage()]]], 403);
        }

        return response()->json(['data' => [
            'id' => $message->id,
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'is_self' => true,
        ]], 201);
    }

    /**
     * Resolve identity from the Passport guard only. The old controller read
     * `$request->user`, which Laravel fills from request input as well - a
     * client could post user[id]=victim and impersonate another buyer.
     */
    private function identity(Request $request): array
    {
        $user = auth('api')->user();

        return [
            'user_id' => $user ? (int) $user->id : null,
            'guest_id' => $user ? null : ($request->input('guest_id')
                ? (string) $request->input('guest_id')
                : null),
            'name' => $user ? ($user->full_name ?: 'Member') : 'Guest',
        ];
    }

    private function isOrganizer(Request $request, GroupBuy $group): bool
    {
        $identity = $this->identity($request);
        if ($identity['user_id']) {
            return (bool) $group->organizer_id && (int) $group->organizer_id === $identity['user_id'];
        }

        return (bool) ($group->organizer_guest_id && $identity['guest_id']
            && $group->organizer_guest_id === $identity['guest_id']);
    }

    private function isParticipantOrganizer(GroupBuy $group, GroupBuyParticipant $participant): bool
    {
        if ($group->organizer_id && (int) $participant->user_id === (int) $group->organizer_id) {
            return true;
        }

        return (bool) ($group->organizer_guest_id && $participant->guest_id
            && $participant->guest_id === $group->organizer_guest_id);
    }

    private function isInsider(Request $request, GroupBuy $group): bool
    {
        if ($this->isOrganizer($request, $group)) {
            return true;
        }

        $identity = $this->identity($request);
        if (! $identity['user_id'] && ! $identity['guest_id']) {
            return false;
        }

        return GroupBuyParticipant::where('group_buy_id', $group->id)
            ->when($identity['user_id'], fn ($q) => $q->where('user_id', $identity['user_id']))
            ->when(! $identity['user_id'] && $identity['guest_id'], fn ($q) => $q->where('guest_id', $identity['guest_id']))
            ->exists();
    }

    private function participantFor(Request $request, GroupBuy $group): ?GroupBuyParticipant
    {
        $identity = $this->identity($request);
        $userId = $identity['user_id'];
        $guestId = $identity['guest_id'];

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
