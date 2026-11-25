<?php

namespace App\Http\Controllers;

use App\Models\MarketerApplication;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Services\MarketerAccessService;
use App\Services\MarketerCommissionService;
use App\Support\PayoutRails;
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
    public function __construct(
        private MarketerCommissionService $commissions,
        private MarketerAccessService $access
    ) {
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

        $stores = \App\Models\Store::withoutGlobalScope(\App\Scopes\ZoneScope::class)
            ->where('marketer_id', $marketer->id)
            ->orderBy('name')
            ->get();

        $storeIds = $stores->pluck('id')->all();
        $orderStats = ! empty($storeIds)
            ? \App\Models\Order::withoutGlobalScope(\App\Scopes\ZoneScope::class)
                ->select(
                    'store_id',
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as delivered_orders_count'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(order_amount), 0) as total_sales')
                )
                ->whereIn('store_id', $storeIds)
                ->where('order_status', 'delivered')
                ->groupBy('store_id')
                ->get()
                ->keyBy('store_id')
            : collect();

        $commissionStats = ! empty($storeIds)
            ? MarketerCommission::select(
                    'store_id',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(amount), 0) as commission_earned')
                )
                ->where('marketer_id', $marketer->id)
                ->whereIn('store_id', $storeIds)
                ->whereNotIn('status', [MarketerCommission::STATUS_VOID])
                ->groupBy('store_id')
                ->get()
                ->keyBy('store_id')
            : collect();

        $stores = $stores->map(function ($store) use ($orderStats, $commissionStats) {
            $os = $orderStats->get($store->id);
            $cs = $commissionStats->get($store->id);
            $store->delivered_orders_count = (int) ($os->delivered_orders_count ?? 0);
            $store->total_sales = round((float) ($os->total_sales ?? 0), 2);
            $store->commission_earned = round((float) ($cs->commission_earned ?? 0), 2);
            return $store;
        });

        // Badges must count this marketer's work, not the whole platform's.
        // They use the same scoped builders as the queue screens below.
        $backyardQuery = $this->access->backyardQuery($marketer);
        $pendingBackyardCount = $backyardQuery
            ? $backyardQuery->where('status', \App\Models\BackyardFarmApplication::STATUS_PENDING)->count()
            : 0;

        $tourQuery = $this->access->tourQuery($marketer);
        $upcomingToursCount = $tourQuery
            ? $tourQuery->whereIn('status', ['upcoming', 'open', 'in_progress'])->count()
            : 0;

        return view('marketer.dashboard', compact(
            'marketer',
            'summary',
            'recent',
            'payouts',
            'stores',
            'pendingBackyardCount',
            'upcomingToursCount'
        ));
    }

    public function verifyStore(Request $request, int $storeId)
    {
        $marketer = Auth::guard('admin')->user();
        // Ownership is by attribution, not by the logged-in admin's zone. The
        // rest of this portal deliberately lifts ZoneScope for the same reason;
        // leaving it here would make a store visible on the dashboard but
        // impossible to verify.
        $store = \App\Models\Store::withoutGlobalScope(\App\Scopes\ZoneScope::class)
            ->where('id', $storeId)
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

        // Credit the onboarding bounty only if the store already meets the
        // bounty policy (including administrative approval `status === 1`).
        // If the store is still awaiting Admin approval, field verification is
        // recorded and the bounty will be credited once Admin approves the store.
        $credited = $this->commissions->creditBounty($marketer, $store->fresh());
        $message = $credited
            ? "Verified active farming status for {$store->name}. Onboarding bounty credited."
            : "Recorded field verification for {$store->name}. Onboarding bounty unlocks upon Admin store approval.";

        return back()->with('status', $message);
    }

    public function backyardFarms(Request $request)
    {
        $marketer = Auth::guard('admin')->user();
        $status = $request->query('status', 'pending');

        $backyardQuery = $this->access->backyardQuery($marketer);
        $applications = $backyardQuery
            ? $backyardQuery->with(['zone:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
            : collect();

        return view('marketer.backyard-farms', compact('marketer', 'applications', 'status'));
    }

    public function verifyBackyardFarm(Request $request, int $id)
    {
        $marketer = Auth::guard('admin')->user();
        $backyardQuery = $this->access->backyardQuery($marketer);
        $application = $backyardQuery
            ? $backyardQuery->findOrFail($id)
            : abort(404);

        if ($application->status !== \App\Models\BackyardFarmApplication::STATUS_PENDING) {
            return back()->withErrors(['backyard' => 'This application has already been processed.']);
        }

        $data = $request->validate([
            'action' => 'required|in:verify,reject',
            'verification_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($data['action'] === 'verify') {
            $update = [
                'status' => \App\Models\BackyardFarmApplication::STATUS_VERIFIED,
                'verified_by' => $marketer->id,
                'verified_at' => now(),
                'verification_notes' => $data['verification_notes'] ?? null,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('backyard_farm_applications', 'marketer_id') && ! $application->marketer_id) {
                $update['marketer_id'] = $marketer->id;
            }
            $application->update($update);
            return back()->with('status', "Field-verified {$application->farm_name}. It is now ready for admin approval.");
        }

        $application->update([
            'status' => \App\Models\BackyardFarmApplication::STATUS_REJECTED,
            'verified_by' => $marketer->id,
            'verified_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? 'Did not meet field verification criteria.',
        ]);

        return back()->with('status', "Marked {$application->farm_name} as rejected.");
    }

    public function regionalTours(Request $request)
    {
        $marketer = Auth::guard('admin')->user();
        $region = trim((string) $request->query('region', ''));

        $tourQuery = $this->access->tourQuery($marketer);
        $tours = $tourQuery
            ? $tourQuery->withCount('registrations')
                ->when($region !== '', fn ($q) => $q->where('region', $region))
                ->orderBy('start_date')
                ->get()
            : collect();

        $registrationQuery = $this->access->registrationQuery($marketer);
        $registrations = $registrationQuery
            ? $registrationQuery->with(['tour:id,title,region,start_date'])
                ->when($region !== '', fn ($q) => $q->where('region', $region))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
            : collect();

        $regions = $this->access->regionsFor($marketer);

        return view('marketer.regional-tours', compact('marketer', 'tours', 'registrations', 'regions', 'region'));
    }

    public function updateTourRegistrationStatus(Request $request, int $id)
    {
        $marketer = Auth::guard('admin')->user();
        $registrationQuery = $this->access->registrationQuery($marketer);
        $reg = $registrationQuery
            ? $registrationQuery->findOrFail($id)
            : abort(404);

        $data = $request->validate([
            'status' => 'required|in:registered,confirmed,visited,onboarded,no_show',
            'notes' => 'nullable|string|max:1000',
        ]);

        $update = [
            'status' => $data['status'],
            'marketer_id' => $reg->marketer_id ?: $marketer->id,
        ];
        if (in_array($data['status'], ['visited', 'onboarded'], true) && ! $reg->checked_in_at) {
            $update['checked_in_at'] = now();
        }
        if (! empty($data['notes'])) {
            $update['notes'] = $data['notes'];
        }
        $reg->update($update);

        return back()->with('status', "Updated {$reg->farmer_name} to {$data['status']}.");
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

        $countries = PayoutRails::countryOptions();
        $networksByCountry = collect(PayoutRails::countries())
            ->map(fn (array $rail) => collect($rail['networks'] ?? [])
                ->map(fn (array $network) => (string) ($network['label'] ?? ''))
                ->all())
            ->all();

        return view('marketer.payout-request', compact(
            'marketer',
            'summary',
            'countries',
            'networksByCountry'
        ));
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
            'current_password' => 'required|string',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check((string) $data['current_password'], (string) $marketer->password)) {
            return back()->withInput($request->except('current_password'))
                ->withErrors(['current_password' => 'Your password confirmation did not match.']);
        }

        // An account created from an application proved ownership of one payout
        // number at application time. Do not let a different number be swapped
        // in at withdrawal time. Legacy marketers without an application are
        // unaffected.
        $application = MarketerApplication::where('created_admin_id', $marketer->id)
            ->where('status', MarketerApplication::STATUS_APPROVED)
            ->first();
        if ($application && $this->normalizePayoutNumber($data['account_number']) !== $this->normalizePayoutNumber($application->phone)) {
            return back()->withInput($request->except('current_password'))
                ->withErrors(['account_number' => 'Use the mobile money number you verified when you applied.']);
        }

        $details = $this->validatePayoutRail($data);

        try {
            $payout = $this->commissions->requestPayout($marketer, $details);
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('current_password'))
                ->withErrors(['payout' => $e->getMessage()]);
        }

        return redirect()->route('marketer.payouts')
            ->with('status', 'Payout request ' . $payout->id . ' submitted for approval.');
    }

    /**
     * Canonical rail validation. Returns the normalised values to persist.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validatePayoutRail(array $data): array
    {
        return PayoutRails::validate($data);
    }

    private function normalizePayoutNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return str_starts_with($digits, '233') && strlen($digits) === 12
            ? '0' . substr($digits, 3)
            : $digits;
    }

    public function showRegisterFarmer()
    {
        $marketer = Auth::guard('admin')->user();
        $zones = \App\Models\Zone::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $modules = \App\Models\Module::where('status', 1)->orderBy('id')->get(['id', 'module_name']);
        $regions = [
            'Greater Accra Region', 'Ashanti Region', 'Eastern Region', 'Central Region',
            'Western Region', 'Western North Region', 'Volta Region', 'Oti Region',
            'Bono Region', 'Bono East Region', 'Ahafo Region', 'Northern Region',
            'Savannah Region', 'North East Region', 'Upper East Region', 'Upper West Region',
        ];

        return view('marketer.register-farmer', compact('marketer', 'zones', 'modules', 'regions'));
    }

    public function storeRegisterFarmer(Request $request)
    {
        $marketer = Auth::guard('admin')->user();

        $data = $request->validate([
            'farm_name' => 'required|string|max:191',
            'farm_type' => 'required|in:commercial,cooperative,backyard,processor',
            'owner_f_name' => 'required|string|max:100',
            'owner_l_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20|unique:vendors,phone',
            'email' => 'required|email|max:191|unique:vendors,email',
            'region' => 'required|string|max:100',
            'district' => 'nullable|string|max:120',
            'zone_id' => 'required|integer|exists:zones,id',
            'module_id' => 'required|integer|exists:modules,id',
            'address' => 'required|string|max:255',
            'primary_crops' => 'nullable|string|max:255',
        ]);

        $tempPass = \Illuminate\Support\Str::password(10, true, true, false, false);

        $vendor = new \App\Models\Vendor();
        $vendor->f_name = $data['owner_f_name'];
        $vendor->l_name = $data['owner_l_name'] ?? '';
        $vendor->email = $data['email'];
        $vendor->phone = $data['phone'];
        $vendor->password = \Illuminate\Support\Facades\Hash::make($tempPass);
        $vendor->status = 0;
        $vendor->save();

        $store = new \App\Models\Store();
        $store->name = $data['farm_name'];
        $store->phone = $data['phone'];
        $store->email = $data['email'];
        $store->address = $data['address'];
        $store->zone_id = (int) $data['zone_id'];
        $store->module_id = (int) $data['module_id'];
        $store->vendor_id = $vendor->id;
        $store->status = 0;
        $store->store_business_model = 'commission';
        $store->delivery_time = '1-2 days';

        $storeCols = \Illuminate\Support\Facades\Schema::getColumnListing('stores');
        if (in_array('marketer_code', $storeCols, true)) {
            $store->marketer_code = $marketer->marketer_code ?: ('MKT-GH-' . str_pad((string) $marketer->id, 3, '0', STR_PAD_LEFT));
        }
        if (in_array('farm_type', $storeCols, true)) {
            $store->farm_type = $data['farm_type'];
        }
        if (in_array('is_backyard', $storeCols, true)) {
            $store->is_backyard = $data['farm_type'] === 'backyard';
        }
        if (in_array('region', $storeCols, true)) {
            $store->region = $data['region'];
        }
        if (in_array('district', $storeCols, true)) {
            $store->district = $data['district'] ?? null;
        }
        if (in_array('cultivation_status', $storeCols, true)) {
            $store->cultivation_status = 'active';
        }
        if (in_array('marketer_verification_notes', $storeCols, true) && ! empty($data['primary_crops'])) {
            $store->marketer_verification_notes = 'Crops: ' . $data['primary_crops'];
        }
        $store->save();

        // Attribution must go through assignMarketer(): it is the only place
        // that stamps marketer_assigned_at, which every commission accrual
        // depends on. Setting marketer_id directly leaves it null and the
        // marketer silently earns nothing from this store.
        if (in_array('marketer_id', $storeCols, true)) {
            $store->assignMarketer((int) $marketer->id);
        }

        return redirect()
            ->route('marketer.dashboard')
            ->with('status', "Registered {$store->name} under your referral code! It is now visible in your dashboard and Admin Remote Marketers queue.");
    }
}
