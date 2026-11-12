<?php

namespace App\Http\Controllers;

use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Services\MarketerCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Read-and-request surface for a remote marketer.
 *
 * There is deliberately no action here that moves money. A marketer can see
 * what they have earned and request a payout; only an admin can approve it and
 * record the mobile-money transfer.
 */
class MarketerPortalController extends Controller
{
    public function __construct(private MarketerCommissionService $commissions)
    {
    }

    public function dashboard()
    {
        $marketer = Auth::guard('admin')->user();
        $summary = $this->commissions->summaryFor($marketer);

        $recent = MarketerCommission::forMarketer($marketer->id)
            ->with(['store', 'order'])
            ->latest('id')
            ->limit(10)
            ->get();

        $payouts = MarketerPayout::forMarketer($marketer->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $stores = \App\Models\Store::where('marketer_id', $marketer->id)
            ->orderBy('name')
            ->get();

        return view('marketer.dashboard', compact('marketer', 'summary', 'recent', 'payouts', 'stores'));
    }

    public function verifyStore(Request $request, int $storeId)
    {
        $marketer = Auth::guard('admin')->user();
        $store = \App\Models\Store::where('id', $storeId)
            ->where('marketer_id', $marketer->id)
            ->firstOrFail();

        $data = $request->validate([
            'cultivation_status' => 'nullable|in:active,farming,harvest_ready',
            'next_harvest_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeColumns = \Illuminate\Support\Facades\Schema::getColumnListing('stores');
        if (in_array('marketer_verified', $storeColumns, true)) {
            $store->marketer_verified = true;
            $store->marketer_verified_at = now();
        }
        if (! empty($data['cultivation_status']) && in_array('cultivation_status', $storeColumns, true)) {
            $store->cultivation_status = $data['cultivation_status'];
        }
        if (array_key_exists('next_harvest_date', $data) && in_array('next_harvest_date', $storeColumns, true)) {
            $store->next_harvest_date = $data['next_harvest_date'] ?: null;
        }
        if (! empty($data['notes']) && in_array('marketer_verification_notes', $storeColumns, true)) {
            $store->marketer_verification_notes = $data['notes'];
        }
        $store->save();

        if (\Illuminate\Support\Facades\Schema::hasColumn('items', 'marketer_verified')) {
            \App\Models\Item::where('store_id', $store->id)->update([
                'marketer_verified' => true,
                'marketer_verified_at' => now(),
            ]);
        }

        return back()->with('status', "Verified active farming status for {$store->name}.");
    }

    public function commissions(Request $request)
    {
        $marketer = Auth::guard('admin')->user();
        $status = $request->query('status');

        $items = MarketerCommission::forMarketer($marketer->id)
            ->when(in_array($status, [
                MarketerCommission::STATUS_PENDING,
                MarketerCommission::STATUS_ELIGIBLE,
                MarketerCommission::STATUS_IN_PAYOUT,
                MarketerCommission::STATUS_PAID,
                MarketerCommission::STATUS_VOID,
            ], true), function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->with(['store', 'order'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = $this->commissions->summaryFor($marketer);

        return view('marketer.commissions', compact('marketer', 'items', 'status', 'summary'));
    }

    public function payouts()
    {
        $marketer = Auth::guard('admin')->user();
        $summary = $this->commissions->summaryFor($marketer);

        $items = MarketerPayout::forMarketer($marketer->id)
            ->latest('id')
            ->paginate(25);

        return view('marketer.payouts', compact('marketer', 'items', 'summary'));
    }

    public function showPayoutRequest()
    {
        $marketer = Auth::guard('admin')->user();
        $summary = $this->commissions->summaryFor($marketer);

        if ($summary['eligible'] <= 0) {
            return redirect()->route('marketer.payouts')
                ->withErrors(['payout' => 'You have no eligible commission to request yet.']);
        }

        return view('marketer.payout-request', compact('marketer', 'summary'));
    }

    public function requestPayout(Request $request)
    {
        $marketer = Auth::guard('admin')->user();

        $data = $request->validate([
            'network' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'country' => 'required|string|max:10',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $payout = $this->commissions->requestPayout($marketer, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['payout' => $e->getMessage()]);
        }

        return redirect()->route('marketer.payouts')
            ->with('status', 'Payout request ' . $payout->id . ' submitted for approval.');
    }
}
