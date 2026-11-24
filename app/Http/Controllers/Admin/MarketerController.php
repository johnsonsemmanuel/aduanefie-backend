<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\CentralLogics\SMS_module;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\MarketerAuditLog;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\MarketerCommissionService;
use App\Support\PayoutRails;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    public function index(Request $request)
    {
        $this->authorizeSuperAdmin();

        $selectedRegion = trim((string) $request->query('region', ''));
        $search = trim((string) $request->query('search', ''));
        $roleId = (int) config('marketer.role_id', 5);

        $regions = Schema::hasColumn('admins', 'marketer_region')
            ? Admin::where('role_id', $roleId)
                ->whereNotNull('marketer_region')
                ->where('marketer_region', '!=', '')
                ->distinct()
                ->orderBy('marketer_region')
                ->pluck('marketer_region')
            : collect();

        $marketers = Admin::where('role_id', $roleId)
            ->when($selectedRegion !== '' && Schema::hasColumn('admins', 'marketer_region'), fn ($q) => $q->where('marketer_region', $selectedRegion))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('f_name', 'like', "%{$search}%")
                        ->orWhere('l_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                    if (Schema::hasColumn('admins', 'marketer_code')) {
                        $inner->orWhere('marketer_code', 'like', "%{$search}%");
                    }
                });
            })
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

        return view('admin-views.marketer.index', compact('marketers', 'currency', 'openPayouts', 'regions', 'selectedRegion', 'search'));
    }

    public function commissions(Request $request)
    {
        $this->authorizeSuperAdmin();

        $marketerId = $request->query('marketer_id');
        $status = $request->query('status');
        $storeId = $request->query('store_id');
        $search = trim((string) $request->query('search'));

        $items = $this->commissionsQuery($marketerId, $status, $storeId, $search)
            ->paginate(50)
            ->withQueryString();

        $marketers = Admin::where('role_id', config('marketer.role_id', 5))->orderBy('f_name')->get(['id', 'f_name', 'l_name', 'email']);
        $currency = $this->commissions->currency();

        return view('admin-views.marketer.commissions', compact('items', 'marketers', 'marketerId', 'status', 'storeId', 'search', 'currency'));
    }

    public function commissionsExport(Request $request)
    {
        $this->authorizeSuperAdmin();

        $marketerId = $request->query('marketer_id');
        $status = $request->query('status');
        $storeId = $request->query('store_id');
        $search = trim((string) $request->query('search'));

        $items = $this->commissionsQuery($marketerId, $status, $storeId, $search)
            ->get();

        $filename = 'marketer-commissions-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order ID', 'Store', 'Marketer', 'Marketer Email', 'Delivered At',
                'Platform Commission', 'Rate (%)', 'Marketer Amount', 'Currency',
                'Eligible From', 'Status', 'Payout ID',
            ]);

            foreach ($items as $c) {
                fputcsv($handle, [
                    $c->order_id,
                    $c->store?->name ?? '-',
                    $c->marketer?->f_name . ' ' . $c->marketer?->l_name,
                    $c->marketer?->email ?? '-',
                    $c->order?->delivered ?? '-',
                    number_format($c->base_amount, 2),
                    rtrim(rtrim(number_format($c->rate, 3), '0'), '.'),
                    number_format($c->amount, 2),
                    $c->currency,
                    $c->eligible_at ?? 'on hold',
                    str_replace('_', ' ', $c->status),
                    $c->payout_id ?? '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Shared query builder for commissions listing and export.
     * Returns the Eloquent builder (not paginated) so callers can paginate or ->get().
     */
    private function commissionsQuery(?string $marketerId, ?string $status, ?string $storeId, string $search)
    {
        return MarketerCommission::query()
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
            ->latest('id');
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
            $this->audit('payout.approved', $payout, [], [
                'status' => $payout->status,
                'approved_by' => $payout->approved_by,
                'approved_at' => $payout->approved_at?->toISOString(),
            ]);
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
            $this->audit('payout.rejected', $payout, [], [
                'status' => $payout->status,
                'rejection_reason' => $payout->rejection_reason,
                'rejected_by' => Auth::guard('admin')->user()->id,
            ]);
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
            $this->audit('payout.paid', $payout, [], [
                'status' => $payout->status,
                'reference' => $payout->reference,
                'paid_at' => $payout->paid_at?->toISOString(),
            ]);
        } catch (RuntimeException $e) {
            Toastr::error($e->getMessage());
        }

        return redirect()->route('admin.marketer.payouts.show', $payout->id);
    }

    public function bulkPayout(Request $request)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'commission_ids' => 'required|array|min:1',
            'commission_ids.*' => 'required|integer|exists:marketer_commissions,id',
            'network' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'country' => 'required|string|max:10',
            'note' => 'nullable|string|max:500',
        ]);

        $data = $this->validatePayoutRail($data);

        $commissions = MarketerCommission::query()
            ->whereIn('id', $data['commission_ids'])
            ->where('status', MarketerCommission::STATUS_ELIGIBLE)
            ->lockForUpdate()
            ->get();

        if ($commissions->isEmpty()) {
            Toastr::error('No eligible commissions found for the selected IDs.');
            return back();
        }

        $currencies = $commissions->pluck('currency')->unique();
        if ($currencies->count() > 1) {
            Toastr::error('Selected commissions span more than one currency. Select commissions from one currency only.');
            return back();
        }

        $minimum = (float) config('marketer.min_payout', 0);
        $total = round((float) $commissions->sum('amount'), 2);
        if ($minimum > 0 && $total < $minimum) {
            Toastr::error('The minimum payout is ' . number_format($minimum, 2) . ' ' . $currencies->first() . '.');
            return back();
        }

        $grouped = $commissions->groupBy('marketer_id');
        $created = 0;

        foreach ($grouped as $marketerId => $comms) {
            $marketer = Admin::findOrFail($marketerId);
            $amount = round((float) $comms->sum('amount'), 2);

            $payout = MarketerPayout::create([
                'marketer_id' => $marketerId,
                'amount' => $amount,
                'currency' => $currencies->first(),
                'method' => 'mobile_money',
                'network' => $data['network'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'country' => $data['country'],
                'status' => MarketerPayout::STATUS_REQUESTED,
                'requested_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            MarketerCommission::whereIn('id', $comms->pluck('id'))
                ->update([
                    'payout_id' => $payout->id,
                    'status' => MarketerCommission::STATUS_IN_PAYOUT,
                    'updated_at' => now(),
                ]);

            $this->audit('payout.bulk_created', $payout, [], [
                'commission_count' => $comms->count(),
                'amount' => $amount,
                'currency' => $currencies->first(),
                'network' => $data['network'],
                'country' => $data['country'],
            ]);

            $created++;
        }

        Toastr::success("Created $created payout request(s) for " . number_format($total, 2) . ' ' . $currencies->first() . '.');

        return redirect()->route('admin.marketer.payouts');
    }

    public function updateRate(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'marketer_commission_rate' => 'nullable|numeric|min:0|max:100',
            'onboarding_bounty_ghs' => 'nullable|numeric|min:0|max:10000',
        ]);

        $marketer = Admin::where('id', $id)
            ->where('role_id', config('marketer.role_id', 5))
            ->firstOrFail();

        $before = [
            'marketer_commission_rate' => $marketer->marketer_commission_rate,
            'onboarding_bounty_ghs' => $marketer->onboarding_bounty_ghs,
        ];

        // Null means "use the configured default". Existing commission rows
        // keep the rate they were accrued at, so a change is not retroactive.
        $marketer->marketer_commission_rate = $data['marketer_commission_rate'] ?? null;
        if (Schema::hasColumn('admins', 'onboarding_bounty_ghs') && array_key_exists('onboarding_bounty_ghs', $data)) {
            $marketer->onboarding_bounty_ghs = $data['onboarding_bounty_ghs'] ?? null;
        }
        $marketer->save();

        $after = [
            'marketer_commission_rate' => $marketer->marketer_commission_rate,
            'onboarding_bounty_ghs' => $marketer->onboarding_bounty_ghs,
        ];

        Toastr::success('Commission rate & onboarding bounty updated.');
        $this->audit('rate.updated', $marketer, $before, $after);

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
        $beforeMarketerId = $store->marketer_id;
        $store->assignMarketer($marketerId);

        // If the store is already verified, this attribution earns the bounty
        // immediately. creditBounty is idempotent, so re-attributing the same
        // marketer cannot pay twice.
        if ($marketerId) {
            $newMarketer = Admin::find($marketerId);
            if ($newMarketer && $newMarketer->isMarketer()) {
                $this->commissions->creditBounty($newMarketer, $store->fresh());
            }
        }

        Toastr::success($marketerId
            ? 'Store attributed. Commission accrues for orders delivered from now on.'
            : 'Marketer removed from the store.');

        $this->audit('store.attributed', $store, [
            'marketer_id' => $beforeMarketerId,
        ], [
            'marketer_id' => $store->marketer_id,
        ]);

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
        $bounties = $this->commissions->syncBounties();
        $result = $this->commissions->refresh();

        Toastr::success(sprintf(
            '%d accrued, %d bounties credited, %d voided, %d held, %d restored, %d released.',
            $created,
            $bounties,
            $result['voided'],
            $result['held'],
            $result['restored'],
            $result['released']
        ));

        return back();
    }

    public function storeMarketer(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->ensureMarketerRoleExists();

        $data = $request->validate([
            'f_name' => 'required|string|max:100',
            'l_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:32',
            'marketer_region' => 'nullable|string|max:100',
            'marketer_code' => 'nullable|string|max:32',
            'marketer_commission_rate' => 'nullable|numeric|min:0|max:100',
            'onboarding_bounty_ghs' => 'nullable|numeric|min:0|max:10000',
            'password' => 'nullable|string|min:6|max:100',
        ]);

        $plainPassword = !empty($data['password']) ? $data['password'] : 'Aduanefie@2026';
        $roleId = (int) config('marketer.role_id', 5);

        $marketer = Admin::where('email', $data['email'])->first() ?? new Admin();
        $marketer->f_name = $data['f_name'];
        $marketer->l_name = $data['l_name'] ?? '';
        $marketer->email = $data['email'];
        $marketer->phone = $data['phone'];
        $marketer->role_id = $roleId;
        $marketer->password = Hash::make($plainPassword);
        if (Schema::hasColumn('admins', 'marketer_region') && !empty($data['marketer_region'])) {
            $marketer->marketer_region = $data['marketer_region'];
        }
        if (array_key_exists('marketer_commission_rate', $data) && $data['marketer_commission_rate'] !== null && $data['marketer_commission_rate'] !== '') {
            $marketer->marketer_commission_rate = $data['marketer_commission_rate'];
        }
        if (Schema::hasColumn('admins', 'onboarding_bounty_ghs') && array_key_exists('onboarding_bounty_ghs', $data) && $data['onboarding_bounty_ghs'] !== null && $data['onboarding_bounty_ghs'] !== '') {
            $marketer->onboarding_bounty_ghs = $data['onboarding_bounty_ghs'];
        }
        $marketer->save();

        if (Schema::hasColumn('admins', 'marketer_code')) {
            $code = !empty($data['marketer_code'])
                ? strtoupper(trim($data['marketer_code']))
                : ($marketer->marketer_code ?: ('MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT)));
            DB::table('admins')->where('id', $marketer->id)->update(['marketer_code' => $code]);
            $marketer->marketer_code = $code;
        }

        if ($request->boolean('send_notification', true)) {
            $this->dispatchMarketerCredentials($marketer, $plainPassword);
        }

        Toastr::success("Remote marketer {$marketer->f_name} saved" . ($request->boolean('send_notification', true) ? ' and notified via Email/SMS.' : '.'));
        return back();
    }

    public function bulkImportMarketers(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->ensureMarketerRoleExists();

        $rawLines = [];
        if ($request->hasFile('csv_file') && $request->file('csv_file')->isValid()) {
            $content = file_get_contents($request->file('csv_file')->getRealPath());
            $rawLines = preg_split('/\r\n|\r|\n/', (string) $content);
        } elseif ($request->filled('bulk_rows')) {
            $rawLines = preg_split('/\r\n|\r|\n/', (string) $request->input('bulk_rows'));
        }

        $roleId = (int) config('marketer.role_id', 5);
        $sendNotify = $request->boolean('send_notification', true);
        $imported = 0;

        foreach ($rawLines as $idx => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if (count($cols) < 3) {
                continue;
            }
            // Skip header row if detected
            if ($idx === 0 && stripos($cols[0], 'first') !== false || stripos($cols[2] ?? '', 'email') !== false) {
                continue;
            }
            $fName = trim($cols[0] ?? '');
            $lName = trim($cols[1] ?? '');
            $email = trim($cols[2] ?? '');
            $phone = trim($cols[3] ?? '');
            $codeInput = trim($cols[4] ?? '');
            $rateInput = isset($cols[5]) && is_numeric(trim($cols[5])) ? (float) trim($cols[5]) : null;

            if ($fName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $plainPassword = 'Aduanefie#' . random_int(1000, 9999);
            $marketer = Admin::where('email', $email)->first() ?? new Admin();
            $marketer->f_name = $fName;
            $marketer->l_name = $lName;
            $marketer->email = $email;
            $marketer->phone = $phone ?: ($marketer->phone ?? '');
            $marketer->role_id = $roleId;
            $marketer->password = Hash::make($plainPassword);
            if ($rateInput !== null) {
                $marketer->marketer_commission_rate = $rateInput;
            }
            $marketer->save();

            if (Schema::hasColumn('admins', 'marketer_code')) {
                $code = $codeInput !== ''
                    ? strtoupper($codeInput)
                    : ($marketer->marketer_code ?: ('MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT)));
                DB::table('admins')->where('id', $marketer->id)->update(['marketer_code' => $code]);
                $marketer->marketer_code = $code;
            }

            if ($sendNotify) {
                $this->dispatchMarketerCredentials($marketer, $plainPassword);
            }
            $imported++;
        }

        if ($imported === 0) {
            Toastr::warning('No valid marketer rows found. Use format: First Name, Last Name, Email, Phone, Code (optional), Rate (optional)');
            return back();
        }

        Toastr::success("Imported {$imported} remote marketer(s)" . ($sendNotify ? ' and dispatched Email/SMS notifications.' : '.'));
        return back();
    }

    public function notifyMarketer(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $marketer = Admin::where('id', $id)
            ->where('role_id', config('marketer.role_id', 5))
            ->firstOrFail();

        $plainPassword = null;
        if ($request->boolean('reset_password', false)) {
            $plainPassword = 'Aduanefie#' . random_int(1000, 9999);
            $marketer->password = Hash::make($plainPassword);
            $marketer->save();
        }

        if (Schema::hasColumn('admins', 'marketer_code') && empty($marketer->marketer_code)) {
            $code = 'MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT);
            DB::table('admins')->where('id', $marketer->id)->update(['marketer_code' => $code]);
            $marketer->marketer_code = $code;
        }

        $this->dispatchMarketerCredentials($marketer, $plainPassword);
        Toastr::success("Dispatched role & onboarding details to {$marketer->f_name} via Email and SMS.");
        return back();
    }

    public function notifyAllMarketers(Request $request)
    {
        $this->authorizeSuperAdmin();

        $region = trim((string) $request->input('region', ''));
        $query = Admin::where('role_id', config('marketer.role_id', 5));
        if ($region !== '' && Schema::hasColumn('admins', 'marketer_region')) {
            $query->where('marketer_region', $region);
        }

        $marketers = $query->get();
        $count = 0;
        foreach ($marketers as $marketer) {
            if (Schema::hasColumn('admins', 'marketer_code') && empty($marketer->marketer_code)) {
                $code = 'MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT);
                DB::table('admins')->where('id', $marketer->id)->update(['marketer_code' => $code]);
                $marketer->marketer_code = $code;
            }
            $this->dispatchMarketerCredentials($marketer, 'Aduanefie@2026');
            $count++;
        }

        Toastr::success("Dispatched Welcome Email & SMS credentials to {$count} remote marketer(s).");
        return back();
    }

    public function sendResetEmail(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $marketer = Admin::where('role_id', config('marketer.role_id', 5))->findOrFail($id);
        $sent = $this->dispatchMarketerAccountResetEmail($marketer);

        if ($sent) {
            Toastr::success("Sent account reset email to {$marketer->email}.");
        } else {
            Toastr::warning("Could not send reset email to {$marketer->email}. Please check the email address or mail settings.");
        }

        return back();
    }

    public function sendBulkResetEmails(Request $request)
    {
        $this->authorizeSuperAdmin();

        $region = trim((string) $request->input('region', ''));
        $query = Admin::where('role_id', config('marketer.role_id', 5));
        if ($region !== '' && Schema::hasColumn('admins', 'marketer_region')) {
            $query->where('marketer_region', $region);
        }

        $marketers = $query->get();
        $sentCount = 0;
        foreach ($marketers as $marketer) {
            if ($this->dispatchMarketerAccountResetEmail($marketer)) {
                $sentCount++;
            }
        }

        Toastr::success("Sent account reset emails to {$sentCount} existing remote marketer(s).");
        return back();
    }

    private function dispatchMarketerAccountResetEmail(Admin $marketer): bool
    {
        if (empty($marketer->email)) {
            return false;
        }

        if (Schema::hasColumn('admins', 'marketer_code') && empty($marketer->marketer_code)) {
            $code = 'MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT);
            DB::table('admins')->where('id', $marketer->id)->update(['marketer_code' => $code]);
            $marketer->marketer_code = $code;
        }

        $refCode = $marketer->marketer_code ?: ('MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT));
        $resetLink = \App\Http\Controllers\MarketerAuthController::buildSignedLoginUrl($marketer, 168);
        $portalUrl = 'https://admin.aduanefie.com/marketer-login';
        $firstName = trim((string) ($marketer->f_name ?: 'Partner'));

        $recipients = [$marketer->email];
        if (str_starts_with(strtolower($marketer->email), 'www.')) {
            $recipients[] = substr($marketer->email, 4);
        }

        $sentAny = false;
        foreach (array_unique($recipients) as $toEmail) {
            if (! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::raw(
                    "Hello {$firstName},\n\n"
                    . "We have upgraded the Aduanefie Remote Marketer Portal. Please reset and activate your account using your secure link below (valid for 7 days):\n\n"
                    . "{$resetLink}\n\n"
                    . "Your Account Details:\n"
                    . "- Login Email: {$toEmail}\n"
                    . "- Referral Code: {$refCode}\n"
                    . "- Marketer Portal: {$portalUrl}\n\n"
                    . "Once you open the link and complete verification, you can set your new password inside your Marketer Dashboard.\n\n"
                    . "Thank you,\nAduanefie Operations Team",
                    function ($message) use ($toEmail, $marketer) {
                        $message->to($toEmail, trim("{$marketer->f_name} {$marketer->l_name}"))
                            ->subject('Reset & Activate Your Aduanefie Marketer Account');
                    }
                );
                $sentAny = true;
            } catch (\Throwable $e) {
                // Continue across recipients
            }
        }

        return $sentAny;
    }

    private function ensureMarketerRoleExists(): void
    {
        $roleId = (int) config('marketer.role_id', 5);
        if (Schema::hasTable('admin_roles') && !DB::table('admin_roles')->where('id', $roleId)->exists()) {
            DB::table('admin_roles')->insert([
                'id' => $roleId,
                'name' => 'Remote Marketer',
                'modules' => json_encode([]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function dispatchMarketerCredentials(Admin $marketer, ?string $plainPassword = null): void
    {
        $refCode = $marketer->marketer_code ?: ('MKT-' . str_pad((string) $marketer->id, 4, '0', STR_PAD_LEFT));
        $portalUrl = url('/marketer-login');
        $onboardUrl = 'https://aduanefie.com/store-registration?ref=' . urlencode($refCode);
        $rate = rtrim(rtrim(number_format($marketer->marketerCommissionRate(), 2, '.', ''), '0'), '.');
        $bounty = number_format($marketer->marketerOnboardingBounty(), 2);
        $regionText = !empty($marketer->marketer_region) ? " ({$marketer->marketer_region})" : '';

        $smsText = "Hello {$marketer->f_name}, welcome to Aduanefie Remote Field Marketers{$regionText}! "
            . "Earn GHc {$bounty}/farmer onboarded + {$rate}% sales commission. "
            . "Code: {$refCode}. Link: {$onboardUrl}. "
            . "Portal: {$portalUrl}"
            . ($plainPassword ? " | Password: {$plainPassword}" : '');

        if (!empty($marketer->phone) && !str_starts_with((string) $marketer->phone, '000000')) {
            try {
                SMS_module::hubtel($marketer->phone, $refCode, $smsText);
            } catch (\Throwable $e) {
                // Continue even if SMS gateway is unreachable
            }
        }

        if (!empty($marketer->email)) {
            $recipients = [$marketer->email];
            if (str_starts_with(strtolower($marketer->email), 'www.')) {
                $recipients[] = substr($marketer->email, 4);
            }
            foreach (array_unique($recipients) as $toEmail) {
                if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                try {
                    Mail::raw(
                        "Hello {$marketer->f_name} {$marketer->l_name},\n\n"
                        . "Welcome to the Aduanefie Remote Field Marketer Programme{$regionText}!\n\n"
                        . "Your Role & Earnings Structure:\n"
                        . "- Onboarding Bounty: GH₵ {$bounty} for each verified farmer / agro-stakeholder you bring onto Aduanefie.\n"
                        . "- Ongoing Sales Commission: {$rate}% of platform commission on every delivered order from stores attributed to you.\n"
                        . "- Field Verification: Verify active farming and harvest readiness from your Marketer Portal.\n\n"
                        . "Your Marketer Portal & Referral Details:\n"
                        . "- Assigned Region: " . ($marketer->marketer_region ?: 'Ghana') . "\n"
                        . "- Farmer Referral Code: {$refCode}\n"
                        . "- Farmer Onboarding Link: {$onboardUrl}\n"
                        . "- Marketer Portal Login: {$portalUrl}\n"
                        . "- Login Email: {$marketer->email}\n"
                        . ($plainPassword ? "- Password: {$plainPassword}\n" : '')
                        . "\nThank you,\nAduanefie Operations Team",
                        function ($message) use ($toEmail, $marketer) {
                            $message->to($toEmail, trim("{$marketer->f_name} {$marketer->l_name}"))
                                ->subject('Your Aduanefie Remote Field Marketer Portal, Earnings & Referral Code');
                        }
                    );
                } catch (\Throwable $e) {
                    // Continue even if SMTP is not configured
                }
            }
        }
    }

    private function authorizeSuperAdmin(): void
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || (int) $admin->role_id !== 1) {
            abort(403);
        }
    }

    /**
     * Write an immutable audit log entry for a marketer programme action.
     *
     * @param  mixed  $subject  Eloquent model or null
     * @param  array  $before   Previous values
     * @param  array  $after    New values
     */
    private function audit(string $action, $subject = null, array $before = [], array $after = []): void
    {
        $admin = Auth::guard('admin')->user();

        MarketerAuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_id' => $subject ? (int) $subject->id : null,
            'subject_type' => $subject ? class_basename($subject) : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
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
}
