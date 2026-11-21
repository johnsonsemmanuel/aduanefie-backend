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

        $pendingBackyardCount = \Illuminate\Support\Facades\Schema::hasTable('backyard_farm_applications')
            ? \App\Models\BackyardFarmApplication::where('status', \App\Models\BackyardFarmApplication::STATUS_PENDING)->count()
            : 0;

        $upcomingToursCount = \Illuminate\Support\Facades\Schema::hasTable('regional_tours')
            ? \App\Models\RegionalTour::whereIn('status', ['upcoming', 'open', 'in_progress'])->count()
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

    public function backyardFarms(Request $request)
    {
        $marketer = Auth::guard('admin')->user();
        $status = $request->query('status', 'pending');

        $applications = \Illuminate\Support\Facades\Schema::hasTable('backyard_farm_applications')
            ? \App\Models\BackyardFarmApplication::with(['zone:id,name'])
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
        $application = \App\Models\BackyardFarmApplication::findOrFail($id);

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

        $tours = \Illuminate\Support\Facades\Schema::hasTable('regional_tours')
            ? \App\Models\RegionalTour::withCount('registrations')
                ->when($region !== '', fn ($q) => $q->where('region', $region))
                ->orderBy('start_date')
                ->get()
            : collect();

        $registrations = \Illuminate\Support\Facades\Schema::hasTable('regional_tour_registrations')
            ? \App\Models\RegionalTourRegistration::with(['tour:id,title,region,start_date'])
                ->when($region !== '', fn ($q) => $q->where('region', $region))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
            : collect();

        $regions = \App\Models\RegionalTour::GHANA_REGIONS;

        return view('marketer.regional-tours', compact('marketer', 'tours', 'registrations', 'regions', 'region'));
    }

    public function updateTourRegistrationStatus(Request $request, int $id)
    {
        $marketer = Auth::guard('admin')->user();
        $reg = \App\Models\RegionalTourRegistration::findOrFail($id);

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
            'current_password' => 'required|string',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check((string) $data['current_password'], (string) $marketer->password)) {
            return back()->withInput($request->except('current_password'))
                ->withErrors(['current_password' => 'Your password confirmation did not match.']);
        }

        $this->validatePayoutRail($data);

        try {
            $payout = $this->commissions->requestPayout($marketer, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['payout' => $e->getMessage()]);
        }

        return redirect()->route('marketer.payouts')
            ->with('status', 'Payout request ' . $payout->id . ' submitted for approval.');
    }

    /**
     * Validate that the network is supported for the country and that the
     * account number matches the network's expected pattern.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validatePayoutRail(array $data): void
    {
        $country = strtoupper(trim((string) ($data['country'] ?? '')));
        $network = strtoupper(trim((string) ($data['network'] ?? '')));
        $account = preg_replace('/\D/', '', (string) ($data['account_number'] ?? ''));

        $rails = config('payout.countries', []);
        $countryConfig = $rails[$country] ?? null;

        if (!$countryConfig) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['country' => $data['country'] ?? ''],
                ['country' => 'required|in:' . implode(',', array_keys($rails))],
                ['country.in' => 'The selected country is not supported for payouts yet.']
            );
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $networks = $countryConfig['networks'];
        if (!array_key_exists($network, $networks)) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['network' => $network],
                ['network' => 'required|in:' . implode(',', array_keys($networks))],
                ['network.in' => 'That network is not supported in ' . $countryConfig['name'] . '.']
            );
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $pattern = $networks[$network]['pattern'] ?? null;
        if ($pattern && !preg_match('#' . $pattern . '#', $account)) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['account_number' => $data['account_number'] ?? ''],
                ['account_number' => 'required|regex:' . $pattern],
                [
                    'account_number.regex' => 'The account number does not look like a valid '
                        . $networks[$network]['label'] . ' number for ' . $countryConfig['name'] . '.',
                ]
            );
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }
}
