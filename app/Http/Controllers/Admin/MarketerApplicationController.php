<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketerAuthController;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\Order;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\MarketerCommissionService;
use App\Traits\SmsGateway;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MarketerApplicationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('q', ''));

        $applications = MarketerApplication::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $roleId = (int) config('marketer.role_id', 5);
        $activeMarketers = Admin::where('role_id', $roleId)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('f_name', 'like', "%{$search}%")
                        ->orWhere('l_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('marketer_code', 'like', "%{$search}%")
                        ->orWhere('marketer_region', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'marketers_page')
            ->withQueryString();

        $totalMarketers = Admin::where('role_id', $roleId)->count();
        $missingCodeCount = Admin::where('role_id', $roleId)
            ->where(fn ($q) => $q->whereNull('marketer_code')->orWhere('marketer_code', ''))
            ->count();

        return view('admin-views.marketer.applications', compact(
            'applications',
            'status',
            'activeMarketers',
            'search',
            'totalMarketers',
            'missingCodeCount'
        ));
    }

    public function approve(Request $request, int $id)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $app = MarketerApplication::findOrFail($id);
        if ($app->status !== MarketerApplication::STATUS_PENDING) {
            Toastr::warning('Application already processed.');
            return back();
        }

        if (Admin::whereRaw('LOWER(email) = ?', [strtolower($app->email)])->exists()) {
            Toastr::error('An admin/marketer account with that email already exists.');
            return back();
        }

        $parts = preg_split('/\s+/', trim($app->full_name), 2);
        $tempPassword = Str::password(12, true, true, false, false);
        $roleId = (int) config('marketer.role_id', 5);

        $admin = new Admin();
        $admin->f_name = $parts[0] ?? $app->full_name;
        $admin->l_name = $parts[1] ?? '';
        $admin->email = $app->email;
        $admin->phone = $app->phone;
        $admin->password = Hash::make($tempPassword);
        $admin->role_id = $roleId;
        if (Schema::hasColumn('admins', 'marketer_region')) {
            $admin->marketer_region = $app->region;
        }
        $admin->save();

        if (Schema::hasColumn('admins', 'marketer_code')) {
            $admin->marketer_code = 'MKT-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $app->region), 0, 3)) . '-' . $admin->id;
            $admin->save();
        }

        $app->update([
            'status' => MarketerApplication::STATUS_APPROVED,
            'reviewed_by' => Auth::guard('admin')->id(),
            'reviewed_at' => now(),
            'created_admin_id' => $admin->id,
        ]);

        try {
            $code = $admin->marketer_code ?: ('MKT-' . $admin->id);
            SmsGateway::send(
                $app->phone,
                "Welcome to Aduanefie! Your Regional Marketer account is active. Login at https://admin.aduanefie.com/marketer-login with {$app->email} / Temp Pass: {$tempPassword}. Your referral code: {$code}"
            );
        } catch (\Throwable $e) {
            // SMS failure should not roll back approval
        }

        Toastr::success("Approved {$app->full_name} and created marketer account (ID #{$admin->id}).");
        return back();
    }

    public function reject(Request $request, int $id)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $app = MarketerApplication::findOrFail($id);
        $app->update([
            'status' => MarketerApplication::STATUS_REJECTED,
            'reviewed_by' => Auth::guard('admin')->id(),
            'reviewed_at' => now(),
            'rejection_reason' => trim((string) $request->input('rejection_reason', 'Did not meet current regional criteria.')),
        ]);

        Toastr::info("Rejected application for {$app->full_name}.");
        return back();
    }

    public function exportCsv(Request $request)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $status = $request->query('status', 'all');
        $apps = MarketerApplication::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();

        $escape = static function ($v): string {
            $s = (string) ($v ?? '');
            if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
                $s = "'" . $s;
            }
            return $s;
        };

        return response()->streamDownload(function () use ($apps, $escape) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Full Name', 'Email', 'Phone', 'Region', 'District', 'MoMo Network', 'Status', 'Applied At']);
            foreach ($apps as $a) {
                fputcsv($out, [
                    $a->id,
                    $escape($a->full_name),
                    $escape($a->email),
                    $escape($a->phone),
                    $escape($a->region),
                    $escape($a->district ?? ''),
                    $escape($a->momo_network ?? ''),
                    $escape($a->status),
                    optional($a->created_at)->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, 'aduanefie-marketer-applications.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function backfillCodes()
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $roleId = (int) config('marketer.role_id', 5);
        $missing = Admin::where('role_id', $roleId)
            ->where(fn ($q) => $q->whereNull('marketer_code')->orWhere('marketer_code', ''))
            ->get();

        foreach ($missing as $m) {
            $slug = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($m->marketer_region ?: 'GH')), 0, 2) ?: 'GH');
            $m->forceFill([
                'marketer_code' => 'MKT-' . $slug . '-' . str_pad((string) $m->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        Toastr::success('Assigned referral codes to ' . $missing->count() . ' marketer(s).');
        return back();
    }

    public function showMarketer(int $id, MarketerCommissionService $commissionService)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $marketer = Admin::where('id', $id)
            ->where('role_id', (int) config('marketer.role_id', 5))
            ->firstOrFail();

        $summary = $commissionService->summaryFor($marketer);

        $stores = Store::withoutGlobalScope(ZoneScope::class)
            ->where('marketer_id', $marketer->id)
            ->orderByDesc('id')
            ->get();

        $storeIds = $stores->pluck('id')->all();
        $orderStats = ! empty($storeIds)
            ? Order::withoutGlobalScope(ZoneScope::class)
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

        $stores = $stores->map(function ($store) use ($orderStats) {
            $os = $orderStats->get($store->id);
            $store->delivered_orders_count = (int) ($os->delivered_orders_count ?? 0);
            $store->total_sales = round((float) ($os->total_sales ?? 0), 2);
            return $store;
        });

        $payouts = MarketerPayout::forMarketer($marketer->id)
            ->latest('id')
            ->limit(15)
            ->get();

        $recentCommissions = MarketerCommission::forMarketer($marketer->id)
            ->with(['store', 'order'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin-views.marketer.show', compact(
            'marketer',
            'summary',
            'stores',
            'payouts',
            'recentCommissions'
        ));
    }

    public function sendMarketerLogin(int $id)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $marketer = Admin::where('id', $id)
            ->where('role_id', (int) config('marketer.role_id', 5))
            ->firstOrFail();

        $res = MarketerAuthController::sendReengagementNotice($marketer, 168);
        $channels = array_filter([
            $res['sms'] ? 'SMS' : null,
            $res['email'] ? 'Email' : null,
        ]);

        if (empty($channels)) {
            Toastr::warning("Could not deliver SMS or Email to {$marketer->f_name}. Check SMS/Mail logs.");
        } else {
            Toastr::success("Sent sign-in link to {$marketer->f_name} via " . implode(' + ', $channels) . '.');
        }

        return back();
    }

    public function sendBatchLogins(Request $request)
    {
        abort_unless(Auth::guard('admin')->user()?->role_id == 1, 403);

        $offset = max(0, (int) $request->input('offset', 0));
        $limit = min(30, max(1, (int) $request->input('limit', 20)));
        $roleId = (int) config('marketer.role_id', 5);

        $batch = Admin::where('role_id', $roleId)
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        $smsOk = 0;
        $emailOk = 0;
        foreach ($batch as $marketer) {
            $res = MarketerAuthController::sendReengagementNotice($marketer, 168);
            if ($res['sms']) {
                $smsOk++;
            }
            if ($res['email']) {
                $emailOk++;
            }
        }

        Toastr::success("Batch ({$batch->count()} marketers from offset {$offset}): {$smsOk} SMS sent, {$emailOk} Emails sent.");
        return back();
    }
}
