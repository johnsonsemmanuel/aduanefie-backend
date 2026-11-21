<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Services\GroupBuy\GroupBuyNotificationService;
use App\Services\GroupBuy\GroupBuyService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class GroupBuyController extends Controller
{
    public function __construct(
        private GroupBuyService $groups,
        private GroupBuyNotificationService $notifications,
    )
    {
    }

    public function index(Request $request)
    {
        $query = GroupBuy::with(['item:id,name', 'store:id,name'])
            ->withCount('participants')
            ->orderByDesc('id');
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return view('admin-views.group-buys.index', [
            'groups' => $query->paginate(20),
            'status' => $request->input('status', 'all'),
        ]);
    }

    public function show($id)
    {
        $group = GroupBuy::with(['item', 'store', 'participants', 'events'])->findOrFail($id);

        return view('admin-views.group-buys.show', [
            'group' => $group,
            'unitPrice' => $this->groups->resolvedUnitPrice($group),
        ]);
    }

    public function cancel($id)
    {
        try {
            $this->groups->cancel(GroupBuy::findOrFail($id));
            Toastr::success('Group cancelled and all members released.');
        } catch (\InvalidArgumentException $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function expire($id)
    {
        try {
            $this->groups->expire(GroupBuy::findOrFail($id));
            Toastr::success('Group expired and all members released.');
        } catch (\InvalidArgumentException $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function ship($participantId)
    {
        $participant = GroupBuyParticipant::findOrFail($participantId);
        $this->groups->markShipped($participant);
        Toastr::success('Member marked as shipped.');

        return back();
    }

    public function deliver($participantId)
    {
        $participant = GroupBuyParticipant::findOrFail($participantId);
        $this->groups->markDelivered($participant);
        Toastr::success('Member marked as delivered.');

        return back();
    }

    public function sendReminder($id)
    {
        $group = GroupBuy::findOrFail($id);
        $this->notifications->paymentReminder($group);
        Toastr::success('Payment reminders sent to unpaid members.');

        return back();
    }

    public function releaseEscrow($id)
    {
        try {
            $this->groups->releaseEscrow(GroupBuy::findOrFail($id));
            Toastr::success('Escrow released to the store.');
        } catch (\InvalidArgumentException $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }
}
