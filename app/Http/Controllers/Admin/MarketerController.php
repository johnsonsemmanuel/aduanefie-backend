<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\MarketerCommissionService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Admin screens for the remote-marketer programme.
 *
 * Restricted to the super admin. These pages are the only place a marketer
 * payout can be approved or recorded as paid, and the only place a store can be
 * attributed, so they are not part of the general employee permission set.
 *
 * Every money rule lives in MarketerCommissionService. This controller only
 * validates input, calls the service, and turns a refused transition into a
 * message instead of a 500.
 */
class MarketerController extends Controller
{
    public function __construct(private MarketerCommissionService $commissions)
    {
    }

    public function index()
    {
        $this->authorizeSuperAdmin();

        $marketers = Admin::where('role_id', config('marketer.role_id', 5))
            ->orderBy('f_name')
            ->get()
            ->map(function (Admin $marketer) {
                return [
                    'admin' => $marketer,
                    'summary' => $this->commissions->summaryFor($marketer),
                ];
            });

        $currency = $this->commissions->currency();

        $openPayouts = MarketerPayout::whereIn('status', [
            MarketerPayout::STATUS_REQUESTED,
            MarketerPayout::STATUS_APPROVED,
        ])->count();

        return view('admin-views.marketer.index', compact('marketers', 'currency', 'openPayouts'));
    }

    public function commissions(Request $request)
    {
        $this->authorizeSuperAdmin();

        $marketerId = $request->query('marketer_id');
        $status = $request->query('status');
        $storeId = $request->query('store_id');
        $search = trim((string) $request->query('search'));

        $items = MarketerCommission::query()
            ->with(['marketer', 'store', 'order'])
            ->when($marketerId, fn ($q) => $q->where('marketer_id', $marketerId))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when(in_array($status, [
                MarketerCommission::STATUS_PENDING,
                MarketerCommission::STATUS_ELIGIBLE,
                MarketerCommission::STATUS_IN_PAYOUT,
                MarketerCommission::STATUS_PAID,
                MarketerCommission::STATUS_VOID,
            ], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_id', $search)
                        ->orWhereHas('store', fn ($s) => $s->withoutGlobalScope(ZoneScope::class)->where('name', 'like', "%$search%"))
                        ->orWhereHas('marketer', fn ($m) => $m->where('email', 'like', "%$search%")->orWhere('f_name', 'like', "%$search%"));
                });
            })
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $marketers = Admin::where('role_id', config('marketer.role_id', 5))->orderBy('f_name')->get(['id', 'f_name', 'l_name', 'email']);
        $currency = $this->commissions->currency();

        return view('admin-views.marketer.commissions', compact('items', 'marketers', 'marketerId', 'status', 'storeId', 'search', 'currency'));
    }

    public function payouts(Request $request)
    {
        $this->authorizeSuperAdmin();

        $status = $request->query('status');
        $marketerId = $request->query('marketer_id');

        $items = MarketerPayout::query()
            ->with('marketer')
            ->when($marketerId, fn ($q) => $q->where('marketer_id', $marketerId))
            ->when(in_array($status, [
                MarketerPayout::STATUS_REQUESTED,
                MarketerPayout::STATUS_APPROVED,
                MarketerPayout::STATUS_PAID,
                MarketerPayout::STATUS_REJECTED,
            ], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $marketers = Admin::where('role_id', config('marketer.role_id', 5))->orderBy('f_name')->get(['id', 'f_name', 'l_name', 'email']);
        $currency = $this->commissions->currency();

        return view('admin-views.marketer.payouts', compact('items', 'marketers', 'marketerId', 'status', 'currency'));
    }

    public function showPayout($id)
    {
        $this->authorizeSuperAdmin();

        $payout = MarketerPayout::with([
            'marketer',
            'approver',
            'commissions' => fn ($q) => $q->with([
                'store' => fn ($s) => $s->withoutGlobalScope(ZoneScope::class),
                'order' => fn ($o) => $o->withoutGlobalScope(ZoneScope::class),
            ]),
        ])->findOrFail($id);

        return view('admin-views.marketer.payout', compact('payout'));
    }

    public function approvePayout(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $payout = MarketerPayout::findOrFail($id);

        try {
            $this->commissions->approve($payout, Auth::guard('admin')->user());
            Toastr::success('Payout #' . $payout->id . ' approved. No money has moved yet.');
        } catch (RuntimeException $e) {
            Toastr::error($e->getMessage());
        }

        return redirect()->route('admin.marketer.payouts.show', $payout->id);
    }

    public function rejectPayout(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $request->validate(['reason' => 'required|string|max:255']);
        $payout = MarketerPayout::findOrFail($id);

        try {
            $this->commissions->reject($payout, Auth::guard('admin')->user(), $request->input('reason'));
            Toastr::success('Payout #' . $payout->id . ' rejected. Its commissions are requestable again.');
        } catch (RuntimeException $e) {
            Toastr::error($e->getMessage());
        }

        return redirect()->route('admin.marketer.payouts.show', $payout->id);
    }

    public function markPayoutPaid(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'reference' => 'required|string|max:100',
        ], [
            'reference.required' => 'A mobile-money transfer reference is required.',
        ]);

        $payout = MarketerPayout::findOrFail($id);

        try {
            $this->commissions->markPaid($payout, Auth::guard('admin')->user(), $request->input('reference'));
            Toastr::success('Payout #' . $payout->id . ' recorded as paid.');
        } catch (RuntimeException $e) {
            Toastr::error($e->getMessage());
        }

        return redirect()->route('admin.marketer.payouts.show', $payout->id);
    }

    public function updateRate(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'marketer_commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $marketer = Admin::where('id', $id)
            ->where('role_id', config('marketer.role_id', 5))
            ->firstOrFail();

        // Null means "use the configured default". Existing commission rows
        // keep the rate they were accrued at, so a change is not retroactive.
        $marketer->marketer_commission_rate = $data['marketer_commission_rate'] ?? null;
        $marketer->save();

        Toastr::success('Rate updated. It applies to future commissions only.');

        return back();
    }

    public function stores(Request $request)
    {
        $this->authorizeSuperAdmin();

        $search = trim((string) $request->query('search'));
        $marketerId = $request->query('marketer_id');

        $stores = Store::withoutGlobalScope(ZoneScope::class)
            ->with('marketer')
            ->when($search !== '', function ($q) use ($search) {
                // Grouped: an ungrouped orWhere here would let a name match
                // escape the marketer filter and list stores that are not
                // unattributed / not owned by the selected marketer.
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%");
                });
            })
            ->when($marketerId === 'none', fn ($q) => $q->whereNull('marketer_id'))
            ->when($marketerId && $marketerId !== 'none', fn ($q) => $q->where('marketer_id', $marketerId))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $marketers = Admin::where('role_id', config('marketer.role_id', 5))->orderBy('f_name')->get(['id', 'f_name', 'l_name', 'email']);

        return view('admin-views.marketer.stores', compact('stores', 'marketers', 'search', 'marketerId'));
    }

    public function assignStore(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'marketer_id' => 'nullable|integer|exists:admins,id',
        ]);

        $marketerId = $data['marketer_id'] ?? null;

        if ($marketerId) {
            $isMarketer = Admin::where('id', $marketerId)
                ->where('role_id', config('marketer.role_id', 5))
                ->exists();

            if (!$isMarketer) {
                Toastr::error('That account is not a remote marketer.');
                return back();
            }
        }

        $store = Store::withoutGlobalScope(ZoneScope::class)->findOrFail($id);
        $store->assignMarketer($marketerId);

        Toastr::success($marketerId
            ? 'Store attributed. Commission accrues for orders delivered from now on.'
            : 'Marketer removed from the store.');

        return back();
    }

    /**
     * Run accrual and reconciliation on demand.
     *
     * The same work the hourly command does. Useful immediately after
     * attributing a store, and it is what makes the schedule a convenience
     * rather than a dependency.
     */
    public function accrue()
    {
        $this->authorizeSuperAdmin();

        $created = $this->commissions->accrue();
        $result = $this->commissions->refresh();

        Toastr::success(sprintf(
            '%d accrued, %d voided, %d held, %d restored, %d released.',
            $created,
            $result['voided'],
            $result['held'],
            $result['restored'],
            $result['released']
        ));

        return back();
    }

    private function authorizeSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || (int) $admin->role_id !== 1) {
            abort(403);
        }
    }
}
