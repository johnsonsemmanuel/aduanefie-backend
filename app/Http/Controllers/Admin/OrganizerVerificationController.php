<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GroupBuy\OrganizerTrustService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class OrganizerVerificationController extends Controller
{
    public function __construct(private OrganizerTrustService $trust)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $query = User::query()->whereNotNull('ghana_card_last4');
        if ($status !== 'all') {
            $query->where('organizer_verification_status', $status);
        }

        $users = $query->orderByDesc('organizer_verification_submitted_at')->paginate(20);
        $rows = $users->getCollection()->map(function (User $user) {
            return [
                'user' => $user,
                'trust' => $this->trust->summary($user),
            ];
        });
        $users->setCollection($rows);

        return view('admin-views.organizer-verifications.index', [
            'users' => $users,
            'status' => $status,
        ]);
    }

    public function approve($id)
    {
        $user = User::findOrFail($id);
        if (! $user->ghana_card_last4) {
            Toastr::error('This account has not submitted a Ghana Card reference.');

            return back();
        }

        $user->update([
            'organizer_verification_status' => OrganizerTrustService::STATUS_VERIFIED,
            'organizer_verified_at' => now(),
            'organizer_verification_note' => null,
        ]);
        Toastr::success('Organiser verification approved.');

        return back();
    }

    public function reject(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $user->update([
            'organizer_verification_status' => OrganizerTrustService::STATUS_REJECTED,
            'organizer_verified_at' => null,
            'organizer_verification_note' => $request->input('note'),
        ]);
        Toastr::success('Organiser verification rejected.');

        return back();
    }
}
