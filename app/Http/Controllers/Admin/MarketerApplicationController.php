<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketerApplication;
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
        $applications = MarketerApplication::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin-views.marketer.applications', compact('applications', 'status'));
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
                "Welcome to Aduanefie Partners! Your Regional Marketer account is active. Login at https://partners.aduanefie.com/marketer-login with {$app->email} / Temp Pass: {$tempPassword}. Your referral code: {$code}"
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
}
