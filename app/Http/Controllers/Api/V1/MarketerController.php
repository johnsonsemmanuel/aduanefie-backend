<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Marketer;
use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MarketerController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|string|max:100',
            'l_name' => 'nullable|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|max:30',
            'nid_number' => 'required|string|max:50',
            'nid_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'password' => 'required|string|min:8',
            'referral_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = new User();
        $user->f_name = $request->f_name;
        $user->l_name = $request->l_name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->password = Hash::make($request->password);
        $user->account_type = 'marketer';
        $user->verification_code = rand(1000, 9999);
        $user->save();

        if ($request->hasFile('nid_image')) {
            $nidImage = Helpers::upload('marketer/', 'png', $request->file('nid_image'));
        } else {
            $nidImage = null;
        }

        $marketer = new Marketer();
        $marketer->user_id = $user->id;
        $marketer->nid_number = $request->nid_number;
        $marketer->nid_image = $nidImage;
        $marketer->referral_code = $request->referral_code ?? $this->generateReferralCode();
        $marketer->status = 1;
        $marketer->save();

        return response()->json([
            'message' => translate('messages.registration_successful'),
            'user' => $user,
            'marketer' => $marketer,
        ], 200);
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();

        if ($user->account_type !== 'marketer') {
            return response()->json(['errors' => [['code' => 'unauthorized', 'message' => translate('messages.unauthorized')]]], 401);
        }

        $marketer = Marketer::where('user_id', $user->id)->firstOrFail();

        $referrals = $marketer->referrals()->count();
        $totalEarnings = $marketer->earnings()->where('status', 1)->sum('amount');
        $pendingPayout = $marketer->earnings()->where('status', 0)->sum('amount');
        $paidOut = $marketer->earnings()->where('status', 2)->sum('amount');

        return response()->json([
            'total_referrals' => $referrals,
            'total_earnings' => $totalEarnings,
            'pending_payout' => $pendingPayout,
            'paid_out' => $paidOut,
        ], 200);
    }

    public function referrals(Request $request)
    {
        $user = Auth::user();

        if ($user->account_type !== 'marketer') {
            return response()->json(['errors' => [['code' => 'unauthorized', 'message' => translate('messages.unauthorized')]]], 401);
        }

        $marketer = Marketer::where('user_id', $user->id)->firstOrFail();

        $referrals = $marketer->referrals()
            ->with('referredUser')
            ->latest()
            ->paginate(10);

        $formatted = $referrals->map(function ($item) {
            return [
                'id' => $item->id,
                'customer_name' => $item->referredUser?->f_name ?? $item->referred_name,
                'store_name' => $item->store_name ?? null,
                'commission' => $item->commission ?? 0,
                'status' => $item->status == 1 ? 'paid' : 'pending',
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'referrals' => $formatted,
            'total_size' => $referrals->total(),
        ], 200);
    }

    public function leaderboard(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $marketers = Marketer::with('user')
            ->where('status', 1)
            ->orderByDesc('total_earnings')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $formatted = $marketers->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->user?->f_name . ' ' . $item->user?->l_name,
                'referral_count' => $item->referrals()->count(),
                'total_earnings' => $item->total_earnings,
            ];
        });

        return response()->json([
            'leaderboard' => $formatted,
        ], 200);
    }

    private function generateReferralCode(): string
    {
        return strtoupper(Str::random(8));
    }
}
